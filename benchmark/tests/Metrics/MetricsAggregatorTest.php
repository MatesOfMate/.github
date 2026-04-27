<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Metrics;

use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Adapter\TokenUsage;
use MatesOfMate\Benchmark\Adapter\ToolCall;
use MatesOfMate\Benchmark\Mate\MateMetrics;
use MatesOfMate\Benchmark\Metrics\MetricsAggregator;
use MatesOfMate\Benchmark\Metrics\MetricsBag;
use MatesOfMate\Benchmark\Metrics\MetricsContext;
use MatesOfMate\Benchmark\Runner\CommandResult;
use MatesOfMate\Benchmark\Runner\DiffResult;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MetricsAggregatorTest extends TestCase
{
    public function testAggregatorPopulatesEveryRequiredKeyWithDefaultCollectors(): void
    {
        $aggregator = new MetricsAggregator();
        $bag = $aggregator->aggregate($this->context());

        foreach (MetricsBag::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $bag->toArray());
        }
        foreach (MetricsBag::OPTIONAL_KEYS as $key) {
            $this->assertArrayHasKey($key, $bag->toArray());
        }
    }

    public function testFullyPopulatedRunYieldsExpectedMetrics(): void
    {
        $aggregator = new MetricsAggregator();
        $context = $this->context(
            assistantResult: AssistantRunResult::success(
                stdout: '',
                durationMs: 800.0,
                tokenUsage: new TokenUsage(120, 80),
                toolCalls: [
                    new ToolCall('symfony_logs', startedAtMs: 1500.0),
                    new ToolCall('symfony_logs', startedAtMs: 1700.0),
                    new ToolCall('symfony_profiler', startedAtMs: 1900.0, errored: true),
                ],
            ),
            diff: new DiffResult(
                diff: '',
                stat: '',
                changedFiles: ['a.php', 'b.php'],
                additions: 20,
                deletions: 7,
            ),
            mateMetrics: new MateMetrics(
                enabled: true,
                toolCallCount: 2,
                toolNames: ['symfony_logs'],
                firstToolCallMs: 1500.0,
                toolErrors: 0,
            ),
            setupResults: [$this->successCommand()],
            verificationResults: [$this->successCommand(), $this->failedCommand()],
            totalDurationMs: 1234.0,
        );

        $bag = $aggregator->aggregate($context);

        $this->assertSame(1234.0, $bag->get('duration_ms'));
        $this->assertSame(120, $bag->get('input_tokens'));
        $this->assertSame(80, $bag->get('output_tokens'));
        $this->assertSame(200, $bag->get('total_tokens'));
        $this->assertSame(3, $bag->get('tool_call_count'));
        $this->assertSame(1, $bag->get('tool_error_count'));
        $this->assertSame(1500.0, $bag->get('time_to_first_tool_call_ms'));
        $this->assertSame(1, $bag->get('redundant_tool_call_count'));
        $this->assertSame(2, $bag->get('mate_tool_call_count'));
        $this->assertSame(['symfony_logs'], $bag->get('mate_tool_names'));
        $this->assertSame(1500.0, $bag->get('first_mate_tool_call_ms'));
        $this->assertSame(2, $bag->get('files_changed_count'));
        $this->assertSame(20, $bag->get('diff_added_lines'));
        $this->assertSame(7, $bag->get('diff_removed_lines'));
        $this->assertSame(2, $bag->get('commands_passed'));
        $this->assertSame(1, $bag->get('commands_failed'));
    }

    public function testUnsupportedMetricsRemainNullInsteadOfMissing(): void
    {
        $aggregator = new MetricsAggregator();
        $bag = $aggregator->aggregate($this->context(
            assistantResult: AssistantRunResult::success(stdout: '', durationMs: 0.0),
        ));

        $this->assertNull($bag->get('input_tokens'));
        $this->assertNull($bag->get('output_tokens'));
        $this->assertNull($bag->get('total_tokens'));
        $this->assertNull($bag->get('time_to_first_tool_call_ms'));
        $this->assertNull($bag->get('time_to_first_code_change_ms'));
        $this->assertSame(0, $bag->get('tool_call_count'));
        $this->assertNull($bag->get('files_changed_count'));
    }

    /**
     * @param list<CommandResult> $setupResults
     * @param list<CommandResult> $baselineResults
     * @param list<CommandResult> $verificationResults
     */
    private function context(
        ?AssistantRunResult $assistantResult = null,
        ?DiffResult $diff = null,
        ?MateMetrics $mateMetrics = null,
        array $setupResults = [],
        array $baselineResults = [],
        array $verificationResults = [],
        float $totalDurationMs = 100.0,
    ): MetricsContext {
        return new MetricsContext(
            assistantResult: $assistantResult,
            diff: $diff,
            mateMetrics: $mateMetrics ?? MateMetrics::disabled(),
            setupResults: $setupResults,
            baselineResults: $baselineResults,
            verificationResults: $verificationResults,
            totalDurationMs: $totalDurationMs,
        );
    }

    private function successCommand(): CommandResult
    {
        return new CommandResult(
            command: 'true',
            cwd: '/tmp',
            exitCode: 0,
            stdout: '',
            stderr: '',
            durationMs: 1.0,
            timedOut: false,
        );
    }

    private function failedCommand(): CommandResult
    {
        return new CommandResult(
            command: 'false',
            cwd: '/tmp',
            exitCode: 1,
            stdout: '',
            stderr: 'no',
            durationMs: 1.0,
            timedOut: false,
        );
    }
}
