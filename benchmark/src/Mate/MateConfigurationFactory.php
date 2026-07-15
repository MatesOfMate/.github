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

/**
 * Builds a per-workspace {@see MateConfiguration} for a given scenario run.
 *
 * When Mate is enabled, the factory delegates to a {@see MateProvisioner} to
 * install Mate into the workspace and produce the standard `mcp.json`
 * descriptor that adapters forward as `--mcp-config`. The list of expected
 * tools declared on the scenario is carried alongside on the configuration
 * object so evaluators can score tool usage without re-reading the file.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MateConfigurationFactory
{
    public const ENV_CONFIG = 'MATE_BENCHMARK_CONFIG';
    public const ENV_ENABLED = 'MATE_BENCHMARK_ENABLED';

    public function __construct(
        private readonly ?MateProvisionerInterface $provisioner = null,
    ) {
    }

    public function create(Workspace $workspace, Scenario $scenario, bool $enabled): MateConfiguration
    {
        if (!$enabled) {
            return MateConfiguration::disabled();
        }

        if (!$this->provisioner instanceof MateProvisionerInterface) {
            throw new \LogicException('Mate is enabled for this run but no MateProvisioner was wired into MateConfigurationFactory.');
        }

        $configPath = $this->provisioner->provision($workspace);
        $expectedTools = $this->extractStringList($scenario->expected['expected_tool_calls'] ?? []);
        $expectedToolsAny = $this->extractStringList($scenario->expected['expected_tool_calls_any'] ?? []);

        return MateConfiguration::enabled(
            configPath: $configPath,
            expectedTools: $expectedTools,
            expectedToolsAny: $expectedToolsAny,
            env: [
                self::ENV_ENABLED => '1',
                self::ENV_CONFIG => $configPath,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function extractStringList(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $item) {
            if (\is_string($item) && '' !== $item) {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean));
    }
}
