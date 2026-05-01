<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Mate;

use MatesOfMate\Benchmark\Runner\CommandExecutor;
use MatesOfMate\Benchmark\Runner\Workspace;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Bootstraps a workspace so that `vendor/bin/mate serve` can run inside it.
 *
 * The provisioner writes a generated `composer.json` that pulls in
 * `symfony/ai-mate` and the published Mate extensions, runs `composer install`
 * (using the monorepo's local packages via path repositories), then runs
 * `vendor/bin/mate init && vendor/bin/mate discover`. The init step produces
 * a workspace-local `mcp.json` in the standard `{"mcpServers": {...}}` shape
 * which is what Claude Code's `--mcp-config` flag expects.
 *
 * All work happens before the runner seals the baseline, so the generated
 * files are part of the starting state and never appear in the AI-attributed
 * diff.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MateProvisioner implements MateProvisionerInterface
{
    public const COMPOSER_TIMEOUT_SECONDS = 600;
    public const MATE_TIMEOUT_SECONDS = 120;
    public const MCP_CONFIG_FILE = 'mcp.json';
    private const DEFAULT_LOCAL_SYMFONY_AI_ROOT = '/Users/johannes/Development/ai/mate-improvements';

    /**
     * @param list<array{name: string, path: string, version?: string}> $localPackages
     * @param array<string, string>                                     $requirements
     */
    public function __construct(
        private readonly CommandExecutor $commandExecutor,
        private readonly Filesystem $filesystem,
        private readonly array $localPackages,
        private readonly array $requirements,
    ) {
    }

    public static function withMonorepoDefaults(string $monorepoRoot, ?CommandExecutor $commandExecutor = null, ?Filesystem $filesystem = null): self
    {
        $monorepoRoot = rtrim($monorepoRoot, '/');
        $symfonyAiRoot = getenv('BENCHMARK_LOCAL_SYMFONY_AI_ROOT');
        if (false === $symfonyAiRoot || '' === $symfonyAiRoot) {
            $symfonyAiRoot = self::DEFAULT_LOCAL_SYMFONY_AI_ROOT;
        }
        $symfonyAiRoot = rtrim($symfonyAiRoot, '/');

        return new self(
            commandExecutor: $commandExecutor ?? new CommandExecutor(),
            filesystem: $filesystem ?? new Filesystem(),
            localPackages: array_merge([
                ['name' => 'matesofmate/common', 'path' => $monorepoRoot.'/src/common'],
                ['name' => 'matesofmate/composer-extension', 'path' => $monorepoRoot.'/src/composer-extension'],
                ['name' => 'matesofmate/phpunit-extension', 'path' => $monorepoRoot.'/src/phpunit-extension'],
                ['name' => 'matesofmate/phpstan-extension', 'path' => $monorepoRoot.'/src/phpstan-extension'],
            ], self::symfonyAiMatePackages($symfonyAiRoot)),
            requirements: [
                'php' => '>=8.3',
                'symfony/ai-mate' => '^0.8@dev',
                'symfony/ai-monolog-mate-extension' => '^0.8@dev',
                'symfony/ai-symfony-mate-extension' => '^0.8@dev',
                'matesofmate/common' => '@dev',
                'matesofmate/composer-extension' => '@dev',
                'matesofmate/phpunit-extension' => '@dev',
                'matesofmate/phpstan-extension' => '@dev',
            ],
        );
    }

    /**
     * Provisions the workspace and returns the absolute path to the generated
     * `mcp.json` (the value to forward as `--mcp-config`).
     */
    public function provision(Workspace $workspace): string
    {
        $this->writeComposerJson($workspace);

        // --no-scripts avoids running the workspace's post-install hooks (e.g.
        // Symfony Flex's auto-scripts), which the bare benchmark fixtures do
        // not need and which would otherwise fail.
        $this->commandExecutor->mustExecute(
            'composer install --no-interaction --no-progress --no-scripts',
            $workspace->path,
            stage: 'mate-provision',
            timeoutSeconds: self::COMPOSER_TIMEOUT_SECONDS,
        );

        $this->commandExecutor->mustExecute(
            'vendor/bin/mate init --no-interaction',
            $workspace->path,
            stage: 'mate-provision',
            timeoutSeconds: self::MATE_TIMEOUT_SECONDS,
        );

        $this->customizeMateWorkspaceConfig($workspace);

        $this->commandExecutor->mustExecute(
            'vendor/bin/mate discover',
            $workspace->path,
            stage: 'mate-provision',
            timeoutSeconds: self::MATE_TIMEOUT_SECONDS,
        );

        // `mate discover` writes `mate/AGENT_INSTRUCTIONS.md` and a managed
        // block in `AGENTS.md` describing every available extension. Both
        // files are auto-ingested by the assistants we benchmark (Codex reads
        // AGENTS.md, Claude reads CLAUDE.md/AGENTS.md from cwd), which would
        // pre-load tool guidance into the model's context and contaminate the
        // scenario. The MCP server itself does not need these files at runtime
        // — they are pure agent-facing documentation — so we remove them.
        $this->stripAgentDocumentation($workspace);

        $mcpConfigPath = $workspace->path.'/'.self::MCP_CONFIG_FILE;

        if (!$this->filesystem->exists($mcpConfigPath)) {
            throw new \RuntimeException(\sprintf(
                'Mate provisioning finished without producing the expected mcp.json at "%s".',
                $mcpConfigPath,
            ));
        }

        return $mcpConfigPath;
    }

    private function writeComposerJson(Workspace $workspace): void
    {
        $composerPath = $workspace->path.'/composer.json';

        if ($this->filesystem->exists($composerPath)) {
            // The benchmark fixtures are intentionally minimal and never ship
            // a composer.json; if one shows up here it means something
            // upstream is producing surprising state we should not silently
            // overwrite.
            throw new \RuntimeException(\sprintf(
                'Refusing to provision Mate: workspace already contains a composer.json at "%s".',
                $composerPath,
            ));
        }

        $payload = [
            'name' => 'matesofmate/benchmark-workspace',
            'description' => 'Provisional Mate workspace generated by the benchmark runner.',
            'type' => 'project',
            'license' => 'proprietary',
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
            'require' => $this->requirements,
            'config' => [
                'allow-plugins' => [
                    'php-http/discovery' => true,
                    'symfony/flex' => true,
                    'symfony/runtime' => true,
                ],
                'sort-packages' => true,
            ],
            'repositories' => array_map(
                static fn (array $package): array => [
                    'type' => 'path',
                    'url' => $package['path'],
                    'options' => array_filter([
                        'symlink' => true,
                        'versions' => isset($package['version']) ? [$package['name'] => $package['version']] : null,
                    ], static fn (mixed $value): bool => null !== $value),
                ],
                $this->localPackages,
            ),
        ];

        $this->filesystem->dumpFile(
            $composerPath,
            json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    private function stripAgentDocumentation(Workspace $workspace): void
    {
        $instructionsPath = $workspace->path.'/mate/AGENT_INSTRUCTIONS.md';
        if ($this->filesystem->exists($instructionsPath)) {
            $this->filesystem->remove($instructionsPath);
        }

        // `AGENTS.md` may have been authored by the fixture itself, so strip
        // only the managed block (delimited by the AI_MATE_INSTRUCTIONS
        // markers) — that block also contains the "Read mate/AGENT_INSTRUCTIONS.md"
        // directive that would otherwise prime the assistant. If nothing else
        // remains, drop the file too.
        $agentsPath = $workspace->path.'/AGENTS.md';
        if (!$this->filesystem->exists($agentsPath)) {
            return;
        }

        $contents = (string) file_get_contents($agentsPath);
        $stripped = trim((string) preg_replace(
            '/<!-- BEGIN AI_MATE_INSTRUCTIONS -->.*?<!-- END AI_MATE_INSTRUCTIONS -->\s*/s',
            '',
            $contents,
        ));

        if ('' === $stripped) {
            $this->filesystem->remove($agentsPath);

            return;
        }

        $this->filesystem->dumpFile($agentsPath, $stripped."\n");
    }

    private function customizeMateWorkspaceConfig(Workspace $workspace): void
    {
        $mateConfigPath = $workspace->path.'/mate/config.php';
        $logsDirectory = $workspace->path.'/var/logs';

        if (!$this->filesystem->exists($mateConfigPath) || !is_dir($logsDirectory)) {
            return;
        }

        $config = (string) file_get_contents($mateConfigPath);
        if (str_contains($config, 'ai_mate_monolog.log_dir')) {
            return;
        }

        $needle = "    \$container->parameters()\n";
        $replacement = "    \$container->parameters()\n        ->set('ai_mate_monolog.log_dir', '%mate.root_dir%/var/logs')\n";

        if (!str_contains($config, $needle)) {
            throw new \RuntimeException(\sprintf(
                'Cannot customize Mate config at "%s": expected parameter section not found.',
                $mateConfigPath,
            ));
        }

        $this->filesystem->dumpFile($mateConfigPath, str_replace($needle, $replacement, $config));
    }

    /**
     * Temporary local path repos so benchmark workspaces can consume in-flight
     * Symfony Mate changes before they are published.
     *
     * @return list<array{name: string, path: string, version?: string}>
     */
    private static function symfonyAiMatePackages(string $symfonyAiRoot): array
    {
        $packages = [
            ['name' => 'symfony/ai-mate', 'path' => $symfonyAiRoot.'/src/mate', 'version' => '0.8.x-dev'],
            ['name' => 'symfony/ai-mate-composer-plugin', 'path' => $symfonyAiRoot.'/src/mate/composer-plugin', 'version' => '0.8.x-dev'],
            ['name' => 'symfony/ai-monolog-mate-extension', 'path' => $symfonyAiRoot.'/src/mate/src/Bridge/Monolog', 'version' => '0.8.x-dev'],
            ['name' => 'symfony/ai-symfony-mate-extension', 'path' => $symfonyAiRoot.'/src/mate/src/Bridge/Symfony', 'version' => '0.8.x-dev'],
        ];

        return array_values(array_filter($packages, static fn (array $package): bool => is_file($package['path'].'/composer.json')));
    }
}
