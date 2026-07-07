<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Adapter;

use MatesOfMate\Benchmark\Adapter\AssistantRunInput;
use MatesOfMate\Benchmark\Adapter\Platform\PlatformAdapter;
use Symfony\AI\Platform\Bridge\Codex\Factory as CodexFactory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Drives the Codex CLI via the `symfony/ai-codex-platform` bridge.
 *
 * The bridge owns subprocess management and JSONL parsing; this adapter only
 * marshals benchmark inputs into `Platform::invoke()` arguments and forces a
 * write-capable sandbox so the assistant can edit workspace files. Override
 * the binary with `BENCHMARK_CODEX_BIN`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CodexAdapter extends PlatformAdapter
{
    public const NAME = 'codex';
    public const DEFAULT_MODEL = 'gpt-5.3-codex';
    public const ENV_BINARY = 'BENCHMARK_CODEX_BIN';

    public static function withDefaults(): self
    {
        $binary = getenv(self::ENV_BINARY);

        return new self(CodexFactory::createPlatform(
            cliBinary: false === $binary || '' === $binary ? null : $binary,
            timeout: 600,
            environment: self::isolatedCodexEnvironment(),
        ), configuredBinary: false === $binary || '' === $binary ? null : $binary, dynamicBinaryResolution: true);
    }

    public function __construct(
        PlatformInterface $platform,
        string $defaultModel = self::DEFAULT_MODEL,
        private readonly ?string $configuredBinary = null,
        private readonly float $timeout = 600,
        private readonly bool $dynamicBinaryResolution = false,
    )
    {
        parent::__construct($platform, $defaultModel);
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(AssistantRunInput $input): AssistantRunResult
    {
        $sessionFailure = $this->preflightSessionStorageCheck();
        if (null !== $sessionFailure) {
            return $sessionFailure;
        }

        if ($this->dynamicBinaryResolution) {
            $binary = $this->resolveCliBinary($input);
            if ($binary !== $this->configuredBinary) {
                return (new self(
                    CodexFactory::createPlatform(
                        cliBinary: $binary,
                        timeout: $this->timeout,
                        environment: self::isolatedCodexEnvironment(),
                    ),
                    $this->defaultModel,
                    $binary,
                    $this->timeout,
                    false,
                ))->run($input);
            }
        }

        return parent::run($input);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildOptions(AssistantRunInput $input): array
    {
        $options = parent::buildOptions($input);

        if ($input->mateConfig->enabled) {
            // `codex exec` always pins approval_policy=never, which causes
            // every MCP tool invocation to be auto-cancelled with
            // "user cancelled MCP tool call". --dangerously-bypass-approvals-and-sandbox
            // is the only documented escape hatch ("Intended solely for running
            // in environments that are externally sandboxed") and the per-attempt
            // benchmark workspace is exactly that. Mutually exclusive with
            // --sandbox: codex forces sandbox_policy to danger-full-access here.
            $options['dangerously_bypass_approvals_and_sandbox'] = true;
        } else {
            // Codex still needs a writable sandbox to apply patches inside the
            // workspace when Mate is off.
            $options['sandbox'] = 'workspace-write';
        }

        if (isset($options['mcp_config']) && \is_string($options['mcp_config']) && '' !== $options['mcp_config']) {
            $options['config'] = $this->buildMcpServerConfigOverrides($options['mcp_config']);
            unset($options['mcp_config']);
        }

        return $options;
    }

    private function preflightSessionStorageCheck(): ?AssistantRunResult
    {
        $codexHome = getenv('CODEX_HOME');
        if (false === $codexHome || '' === $codexHome) {
            $home = getenv('HOME');
            if (false === $home || '' === $home) {
                return null;
            }

            $codexHome = rtrim($home, '/').'/.codex';
        }

        $sessionsDirectory = rtrim($codexHome, '/').'/sessions';

        if (is_dir($sessionsDirectory) && (!$this->isAccessibleDirectory($sessionsDirectory) || !$this->canWriteProbeFile($sessionsDirectory))) {
            return AssistantRunResult::failure(
                errorMessage: \sprintf(
                    'Codex session storage is not accessible at "%s". Fix the directory permissions/ownership before benchmarking.',
                    $sessionsDirectory,
                ),
            );
        }

        if (!is_dir($sessionsDirectory) && is_dir($codexHome) && !is_writable($codexHome)) {
            return AssistantRunResult::failure(
                errorMessage: \sprintf(
                    'Codex cannot create its session storage under "%s". Fix the directory permissions/ownership before benchmarking.',
                    $codexHome,
                ),
            );
        }

        return null;
    }

    private function resolveCliBinary(AssistantRunInput $input): ?string
    {
        $workspaceBinary = $input->workspacePath.'/bin/codex';
        if ($input->mateConfig->enabled && is_file($workspaceBinary) && is_executable($workspaceBinary)) {
            return $workspaceBinary;
        }

        return $this->configuredBinary;
    }

    /**
     * Strip the parent Codex agent's managed-session markers so benchmarked
     * Codex processes run as fresh standalone sessions and can execute MCP
     * tools without inheriting the outer approval policy.
     *
     * @return array<string, string|false>
     */
    private static function isolatedCodexEnvironment(): array
    {
        return [
            'CODEX_THREAD_ID' => false,
            'CODEX_SANDBOX' => false,
            'CODEX_SANDBOX_NETWORK_DISABLED' => false,
        ];
    }

    private function isAccessibleDirectory(string $directory): bool
    {
        return is_readable($directory) && is_writable($directory);
    }

    private function canWriteProbeFile(string $directory): bool
    {
        $probePath = $directory.'/.benchmark-codex-write-probe-'.bin2hex(random_bytes(8));

        try {
            $handle = @fopen($probePath, 'wb');
            if (false === $handle) {
                return false;
            }

            fclose($handle);
            @unlink($probePath);

            return true;
        } catch (\Throwable) {
            @unlink($probePath);

            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function buildMcpServerConfigOverrides(string $mcpConfigPath): array
    {
        if (!(new Filesystem())->exists($mcpConfigPath)) {
            throw new \RuntimeException(\sprintf('Codex Mate config file "%s" does not exist.', $mcpConfigPath));
        }

        $payload = json_decode((string) file_get_contents($mcpConfigPath), true, 512, \JSON_THROW_ON_ERROR);
        $servers = $payload['mcpServers'] ?? null;

        if (!\is_array($servers) || [] === $servers) {
            throw new \RuntimeException(\sprintf('Codex Mate config file "%s" does not contain any MCP servers.', $mcpConfigPath));
        }

        $overrides = [];

        foreach ($servers as $name => $server) {
            if (!\is_string($name) || '' === $name || !\is_array($server)) {
                continue;
            }

            $normalizedName = str_replace('-', '_', $name);
            $command = $server['command'] ?? null;
            if (!\is_string($command) || '' === $command) {
                throw new \RuntimeException(\sprintf('Codex Mate server "%s" is missing its command.', $name));
            }

            $overrides[] = \sprintf('mcp_servers.%s.command=%s', $normalizedName, json_encode($command, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));

            $args = $server['args'] ?? [];
            if (!\is_array($args)) {
                throw new \RuntimeException(\sprintf('Codex Mate server "%s" has invalid args.', $name));
            }

            $cleanArgs = [];
            foreach ($args as $arg) {
                if (\is_scalar($arg)) {
                    $cleanArgs[] = (string) $arg;
                }
            }

            $overrides[] = \sprintf('mcp_servers.%s.args=%s', $normalizedName, json_encode($cleanArgs, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
        }

        if ([] === $overrides) {
            throw new \RuntimeException(\sprintf('Codex Mate config file "%s" did not yield any valid MCP server overrides.', $mcpConfigPath));
        }

        return $overrides;
    }
}
