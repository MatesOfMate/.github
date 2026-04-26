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

use MatesOfMate\Benchmark\Mate\MateConfigurationFactory;
use MatesOfMate\Benchmark\Runner\Workspace;
use MatesOfMate\Benchmark\Scenario\Scenario;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MateConfigurationFactoryTest extends TestCase
{
    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-mate-cfg-'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testDisabledShortCircuits(): void
    {
        $factory = new MateConfigurationFactory();
        $config = $factory->create($this->workspace(), $this->scenario([]), enabled: false);

        $this->assertFalse($config->enabled);
        $this->assertFileDoesNotExist($this->tmp.'/.mate/config.json');
    }

    public function testEnabledWritesConfigFileAndCarriesExpectedTools(): void
    {
        $factory = new MateConfigurationFactory();
        $scenario = $this->scenario([
            'expected' => [
                'expected_tool_calls' => ['symfony_logs', 'symfony_logs', 'symfony_container'],
            ],
        ]);

        $config = $factory->create($this->workspace(), $scenario, enabled: true);

        $this->assertTrue($config->enabled);
        $this->assertSame(['symfony_logs', 'symfony_container'], $config->expectedTools);
        $this->assertNotNull($config->configPath);
        $this->assertFileExists($config->configPath);

        $payload = json_decode((string) file_get_contents($config->configPath), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(['symfony_logs', 'symfony_container'], $payload['expected_tools']);

        $this->assertSame($config->configPath, $config->env[MateConfigurationFactory::ENV_CONFIG]);
        $this->assertSame('1', $config->env[MateConfigurationFactory::ENV_ENABLED]);
    }

    public function testEnabledWithoutExpectedToolsStillProducesConfig(): void
    {
        $factory = new MateConfigurationFactory();
        $config = $factory->create($this->workspace(), $this->scenario([]), enabled: true);

        $this->assertTrue($config->enabled);
        $this->assertSame([], $config->expectedTools);
        $this->assertFileExists((string) $config->configPath);
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

    /**
     * @param array<string, mixed> $overrides
     */
    private function scenario(array $overrides): Scenario
    {
        return Scenario::fromArray(array_replace_recursive([
            'id' => 'mate.cfg',
            'suite' => 'mate',
            'fixture' => ['path' => $this->tmp],
            'task' => ['prompt' => 'use mate'],
            'expected' => ['pass_commands' => []],
        ], $overrides), '/virtual/mate.cfg.yaml');
    }
}
