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

use MatesOfMate\Benchmark\Runner\Workspace;
use MatesOfMate\Benchmark\Scenario\Scenario;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Builds a per-workspace {@see MateConfiguration} for a given scenario run.
 *
 * When Mate is enabled, the factory writes a small descriptor file
 * (`.mate/config.json`) into the workspace listing the tools the scenario
 * expects. Real adapter implementations can pick this up to provision MCP
 * servers; the file is otherwise informational.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MateConfigurationFactory
{
    public const CONFIG_RELATIVE_PATH = '.mate/config.json';
    public const ENV_CONFIG = 'MATE_BENCHMARK_CONFIG';
    public const ENV_ENABLED = 'MATE_BENCHMARK_ENABLED';

    private readonly Filesystem $filesystem;

    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function create(Workspace $workspace, Scenario $scenario, bool $enabled): MateConfiguration
    {
        if (!$enabled) {
            return MateConfiguration::disabled();
        }

        $expectedTools = $this->extractExpectedTools($scenario);
        $configPath = $workspace->path.'/'.self::CONFIG_RELATIVE_PATH;

        $payload = [
            'workspace' => $workspace->path,
            'scenario' => $scenario->id,
            'expected_tools' => $expectedTools,
        ];

        $this->filesystem->mkdir(\dirname($configPath));
        $this->filesystem->dumpFile(
            $configPath,
            json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
        );

        return MateConfiguration::enabled(
            configPath: $configPath,
            expectedTools: $expectedTools,
            env: [
                self::ENV_ENABLED => '1',
                self::ENV_CONFIG => $configPath,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function extractExpectedTools(Scenario $scenario): array
    {
        $tools = $scenario->expected['expected_tool_calls'] ?? [];
        if (!\is_array($tools)) {
            return [];
        }

        $clean = [];
        foreach ($tools as $tool) {
            if (\is_string($tool) && '' !== $tool) {
                $clean[] = $tool;
            }
        }

        return array_values(array_unique($clean));
    }
}
