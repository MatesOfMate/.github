<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Evaluator\Support;

use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Mate\MateMetrics;
use MatesOfMate\Benchmark\Metrics\MetricsBag;
use MatesOfMate\Benchmark\Runner\CommandResult;
use MatesOfMate\Benchmark\Runner\DiffResult;
use MatesOfMate\Benchmark\Runner\RunOutcome;
use MatesOfMate\Benchmark\Runner\RunStatus;
use MatesOfMate\Benchmark\Runner\Workspace;
use MatesOfMate\Benchmark\Scenario\Scenario;

/**
 * Test helper for assembling realistic but minimal {@see RunOutcome} fixtures.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RunOutcomeBuilder
{
    /**
     * @param array<string, mixed> $scenarioOverrides
     * @param array<string, mixed> $metricsOverrides
     * @param list<CommandResult>  $verificationResults
     */
    public static function build(
        array $scenarioOverrides = [],
        ?AssistantRunResult $assistantResult = null,
        ?DiffResult $diff = null,
        ?MateMetrics $mateMetrics = null,
        array $verificationResults = [],
        RunStatus $status = RunStatus::Passed,
        array $metricsOverrides = [],
        float $totalDurationMs = 100.0,
    ): RunOutcome {
        $scenario = Scenario::fromArray(array_replace_recursive([
            'id' => 'eval.test',
            'suite' => 'unit',
            'difficulty' => 'easy',
            'fixture' => ['path' => '/tmp/fixture'],
            'task' => ['prompt' => 'do something'],
            'expected' => ['pass_commands' => []],
        ], $scenarioOverrides), '/virtual/eval.test.yaml');

        $workspace = new Workspace(
            path: '/tmp/workspace',
            runId: 'run-test',
            scenarioId: $scenario->id,
            attempt: 1,
            keep: false,
        );

        $metrics = MetricsBag::empty()->with($metricsOverrides);

        return new RunOutcome(
            scenario: $scenario,
            workspace: $workspace,
            status: $status,
            setupResults: [],
            baselineResults: [],
            assistantResult: $assistantResult,
            diff: $diff,
            verificationResults: $verificationResults,
            mateMetrics: $mateMetrics ?? MateMetrics::disabled(),
            metrics: $metrics,
            totalDurationMs: $totalDurationMs,
        );
    }

    public static function passingCommand(string $command = 'true'): CommandResult
    {
        return new CommandResult(
            command: $command,
            cwd: '/tmp/workspace',
            exitCode: 0,
            stdout: '',
            stderr: '',
            durationMs: 1.0,
            timedOut: false,
        );
    }

    public static function failingCommand(string $command = 'false', int $exitCode = 1): CommandResult
    {
        return new CommandResult(
            command: $command,
            cwd: '/tmp/workspace',
            exitCode: $exitCode,
            stdout: '',
            stderr: 'failed',
            durationMs: 1.0,
            timedOut: false,
        );
    }
}
