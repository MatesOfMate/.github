<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Mate;

use MatesOfMate\Benchmark\Mate\MateProvisioner;
use MatesOfMate\Benchmark\Runner\CommandExecutor;
use MatesOfMate\Benchmark\Runner\CommandResult;
use MatesOfMate\Benchmark\Runner\Workspace;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MateProvisionerTest extends TestCase
{
    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-mate-prov-'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testWritesComposerJsonAndRunsExpectedCommandSequence(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $invocations = [];
        $executor->expects($this->exactly(3))
            ->method('mustExecute')
            ->willReturnCallback(static function (string $command, string $cwd, ?string $stage = null) use (&$invocations): CommandResult {
                $invocations[] = ['command' => $command, 'cwd' => $cwd, 'stage' => $stage];

                if (str_contains($command, 'vendor/bin/mate init')) {
                    (new Filesystem())->mkdir($cwd.'/mate');
                    (new Filesystem())->dumpFile($cwd.'/mate/config.php', <<<'PHP'
<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // Override default parameters here
    ;

    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
    ;
};
PHP);
                }

                return new CommandResult(
                    command: $command,
                    cwd: $cwd,
                    exitCode: 0,
                    stdout: '',
                    stderr: '',
                    durationMs: 1.0,
                    timedOut: false,
                );
            });

        // The runner expects mate init to produce the mcp.json. Stand in for
        // it by writing the file ourselves so we can assert the provisioner
        // returns its path.
        $this->filesystem->dumpFile($this->tmp.'/mcp.json', '{"mcpServers":{}}');
        $this->filesystem->mkdir($this->tmp.'/var/logs');

        $provisioner = new MateProvisioner(
            commandExecutor: $executor,
            filesystem: $this->filesystem,
            localPackages: [
                ['name' => 'matesofmate/common', 'path' => '/abs/src/common'],
                ['name' => 'symfony/ai-mate', 'path' => '/abs/src/mate', 'version' => '0.10.x-dev'],
            ],
            requirements: [
                'php' => '>=8.3',
                'symfony/ai-mate' => '^0.10@dev',
            ],
        );

        $configPath = $provisioner->provision($this->workspace());

        $this->assertSame($this->tmp.'/mcp.json', $configPath);
        $this->assertCount(3, $invocations);
        $this->assertStringContainsString('composer install', $invocations[0]['command']);
        $this->assertStringContainsString('--no-interaction', $invocations[0]['command']);
        $this->assertStringContainsString('vendor/bin/mate init', $invocations[1]['command']);
        $this->assertStringContainsString('vendor/bin/mate discover', $invocations[2]['command']);
        $this->assertSame($this->tmp, $invocations[0]['cwd']);

        $composer = json_decode((string) file_get_contents($this->tmp.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('matesofmate/benchmark-workspace', $composer['name']);
        $this->assertSame('>=8.3', $composer['require']['php']);
        $this->assertSame('^0.10@dev', $composer['require']['symfony/ai-mate']);
        $this->assertCount(2, $composer['repositories']);
        $this->assertSame('/abs/src/common', $composer['repositories'][0]['url']);
        $this->assertSame('path', $composer['repositories'][0]['type']);
        $this->assertTrue($composer['repositories'][0]['options']['symlink']);
        $this->assertArrayNotHasKey('versions', $composer['repositories'][0]['options']);
        $this->assertSame('/abs/src/mate', $composer['repositories'][1]['url']);
        $this->assertSame(['symfony/ai-mate' => '0.10.x-dev'], $composer['repositories'][1]['options']['versions']);

        $mateConfig = (string) file_get_contents($this->tmp.'/mate/config.php');
        $this->assertStringContainsString("->set('ai_mate_monolog.log_dir', '%mate.root_dir%/var/logs')", $mateConfig);
    }

    public function testRemovesAgentDocumentationLeftBehindByMateDiscover(): void
    {
        $managedBlock = "<!-- BEGIN AI_MATE_INSTRUCTIONS -->\nAI Mate Summary:\n- Required action: Read and follow `mate/AGENT_INSTRUCTIONS.md` ...\n<!-- END AI_MATE_INSTRUCTIONS -->";

        $executor = $this->createMock(CommandExecutor::class);
        $executor->method('mustExecute')->willReturnCallback(static function (string $command, string $cwd) use ($managedBlock): CommandResult {
            if (str_contains($command, 'vendor/bin/mate discover')) {
                $fs = new Filesystem();
                $fs->mkdir($cwd.'/mate');
                $fs->dumpFile($cwd.'/mate/AGENT_INSTRUCTIONS.md', "# instructions\n");
                $fs->dumpFile($cwd.'/AGENTS.md', $managedBlock."\n");
            }

            return new CommandResult(
                command: $command,
                cwd: $cwd,
                exitCode: 0,
                stdout: '',
                stderr: '',
                durationMs: 1.0,
                timedOut: false,
            );
        });

        $this->filesystem->dumpFile($this->tmp.'/mcp.json', '{"mcpServers":{}}');

        $provisioner = new MateProvisioner(
            commandExecutor: $executor,
            filesystem: $this->filesystem,
            localPackages: [],
            requirements: ['php' => '>=8.3'],
        );

        $provisioner->provision($this->workspace());

        // The instructions file leaks tool guidance the assistant would auto-
        // read and follow; remove it. AGENTS.md only contained the managed
        // block (which also references AGENT_INSTRUCTIONS.md), so the file
        // is empty after stripping and gets dropped entirely.
        $this->assertFileDoesNotExist($this->tmp.'/mate/AGENT_INSTRUCTIONS.md');
        $this->assertFileDoesNotExist($this->tmp.'/AGENTS.md');
    }

    public function testStripsManagedBlockFromAgentsFileButPreservesFixtureContent(): void
    {
        $existing = "# Project agent rules\n\nKeep PRs small.\n\n<!-- BEGIN AI_MATE_INSTRUCTIONS -->\nAI Mate Summary:\n- Required action: Read and follow `mate/AGENT_INSTRUCTIONS.md` ...\n<!-- END AI_MATE_INSTRUCTIONS -->\n\n## Other notes\n\nBe nice.\n";

        $executor = $this->createMock(CommandExecutor::class);
        $executor->method('mustExecute')->willReturnCallback(static function (string $command, string $cwd) use ($existing): CommandResult {
            if (str_contains($command, 'vendor/bin/mate discover')) {
                $fs = new Filesystem();
                $fs->dumpFile($cwd.'/AGENTS.md', $existing);
            }

            return new CommandResult(
                command: $command,
                cwd: $cwd,
                exitCode: 0,
                stdout: '',
                stderr: '',
                durationMs: 1.0,
                timedOut: false,
            );
        });

        $this->filesystem->dumpFile($this->tmp.'/mcp.json', '{"mcpServers":{}}');

        $provisioner = new MateProvisioner(
            commandExecutor: $executor,
            filesystem: $this->filesystem,
            localPackages: [],
            requirements: ['php' => '>=8.3'],
        );

        $provisioner->provision($this->workspace());

        $remaining = (string) file_get_contents($this->tmp.'/AGENTS.md');
        $this->assertStringNotContainsString('AGENT_INSTRUCTIONS.md', $remaining);
        $this->assertStringNotContainsString('AI_MATE_INSTRUCTIONS', $remaining);
        $this->assertStringContainsString('Project agent rules', $remaining);
        $this->assertStringContainsString('Be nice.', $remaining);
    }

    public function testRefusesToOverwriteExistingComposerJson(): void
    {
        $this->filesystem->dumpFile($this->tmp.'/composer.json', '{"name":"existing"}');

        $executor = $this->createMock(CommandExecutor::class);
        $executor->expects($this->never())->method('mustExecute');

        $provisioner = new MateProvisioner(
            commandExecutor: $executor,
            filesystem: $this->filesystem,
            localPackages: [],
            requirements: ['php' => '>=8.3'],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already contains a composer.json');
        $provisioner->provision($this->workspace());
    }

    public function testRaisesIfMateInitDidNotProduceMcpJson(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->method('mustExecute')->willReturn(new CommandResult(
            command: 'noop',
            cwd: $this->tmp,
            exitCode: 0,
            stdout: '',
            stderr: '',
            durationMs: 1.0,
            timedOut: false,
        ));

        $provisioner = new MateProvisioner(
            commandExecutor: $executor,
            filesystem: $this->filesystem,
            localPackages: [],
            requirements: ['php' => '>=8.3'],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expected mcp.json');
        $provisioner->provision($this->workspace());
    }

    public function testWithMonorepoDefaultsBuildsRealisticRequirementSet(): void
    {
        // We reach into the object via a single provisioning attempt cut
        // short by the missing-mcp.json check; the side effect we care about
        // (composer.json contents) happens on the first step.
        $executor = $this->createMock(CommandExecutor::class);
        $executor->method('mustExecute')->willReturn(new CommandResult(
            command: 'noop',
            cwd: $this->tmp,
            exitCode: 0,
            stdout: '',
            stderr: '',
            durationMs: 1.0,
            timedOut: false,
        ));

        $provisioner = MateProvisioner::withMonorepoDefaults(
            monorepoRoot: '/abs/monorepo',
            commandExecutor: $executor,
            filesystem: $this->filesystem,
        );

        try {
            $provisioner->provision($this->workspace());
        } catch (\RuntimeException) {
            // Expected: no mcp.json gets produced by the mocked executor.
        }

        $composer = json_decode((string) file_get_contents($this->tmp.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('^0.10@dev', $composer['require']['symfony/ai-mate']);
        $this->assertSame('^0.10@dev', $composer['require']['symfony/ai-monolog-mate-extension']);
        $this->assertSame('^0.10@dev', $composer['require']['symfony/ai-symfony-mate-extension']);
        $this->assertArrayHasKey('matesofmate/composer-extension', $composer['require']);

        $repoUrls = array_column($composer['repositories'], 'url');
        $this->assertContains('/abs/monorepo/src/common', $repoUrls);
        $this->assertContains('/abs/monorepo/src/phpunit-extension', $repoUrls);
    }

    public function testWithMonorepoDefaultsHonorsSymfonyAiRootEnvOverride(): void
    {
        $fakeRoot = $this->tmp.'/fake-symfony-ai';
        $this->filesystem->dumpFile($fakeRoot.'/src/mate/composer.json', '{"name":"symfony/ai-mate"}');
        $this->filesystem->dumpFile($fakeRoot.'/src/mate/composer-plugin/composer.json', '{"name":"symfony/ai-mate-composer-plugin"}');
        $this->filesystem->dumpFile($fakeRoot.'/src/mate/src/Bridge/Monolog/composer.json', '{"name":"symfony/ai-monolog-mate-extension"}');
        $this->filesystem->dumpFile($fakeRoot.'/src/mate/src/Bridge/Symfony/composer.json', '{"name":"symfony/ai-symfony-mate-extension"}');

        $executor = $this->createMock(CommandExecutor::class);
        $executor->method('mustExecute')->willReturn(new CommandResult(
            command: 'noop',
            cwd: $this->tmp,
            exitCode: 0,
            stdout: '',
            stderr: '',
            durationMs: 1.0,
            timedOut: false,
        ));

        putenv('BENCHMARK_SYMFONY_AI_ROOT='.$fakeRoot);

        try {
            $provisioner = MateProvisioner::withMonorepoDefaults(
                monorepoRoot: '/abs/monorepo',
                commandExecutor: $executor,
                filesystem: $this->filesystem,
            );

            try {
                $provisioner->provision($this->workspace());
            } catch (\RuntimeException) {
                // Expected: no mcp.json gets produced by the mocked executor.
            }
        } finally {
            putenv('BENCHMARK_SYMFONY_AI_ROOT');
        }

        $composer = json_decode((string) file_get_contents($this->tmp.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        $repositoriesByUrl = array_column($composer['repositories'], null, 'url');

        $this->assertArrayHasKey($fakeRoot.'/src/mate', $repositoriesByUrl);
        $this->assertArrayHasKey($fakeRoot.'/src/mate/composer-plugin', $repositoriesByUrl);
        $this->assertArrayHasKey($fakeRoot.'/src/mate/src/Bridge/Monolog', $repositoriesByUrl);
        $this->assertArrayHasKey($fakeRoot.'/src/mate/src/Bridge/Symfony', $repositoriesByUrl);
        $this->assertSame(
            ['symfony/ai-mate' => '0.10.x-dev'],
            $repositoriesByUrl[$fakeRoot.'/src/mate']['options']['versions'],
        );
    }

    private function workspace(): Workspace
    {
        return new Workspace(
            path: $this->tmp,
            runId: 'run-test',
            scenarioId: 'mate.cfg',
            attempt: 1,
            keep: true,
        );
    }
}
