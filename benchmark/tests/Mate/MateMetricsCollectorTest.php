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

use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Adapter\ToolCall;
use MatesOfMate\Benchmark\Mate\MateConfiguration;
use MatesOfMate\Benchmark\Mate\MateMetricsCollector;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MateMetricsCollectorTest extends TestCase
{
    public function testDisabledConfigurationProducesEmptyMetrics(): void
    {
        $collector = new MateMetricsCollector();
        $result = AssistantRunResult::success(stdout: '', durationMs: 0.0, toolCalls: [
            new ToolCall('symfony_logs'),
        ]);

        $metrics = $collector->collect($result, MateConfiguration::disabled());

        $this->assertFalse($metrics->enabled);
        $this->assertSame(0, $metrics->toolCallCount);
        $this->assertSame([], $metrics->toolNames);
        $this->assertNull($metrics->firstToolCallMs);
    }

    public function testAggregatesMcpToolCallsOnly(): void
    {
        $collector = new MateMetricsCollector();
        $result = AssistantRunResult::success(stdout: '', durationMs: 0.0, toolCalls: [
            // Built-in tools must not earn Mate credit, not even for the
            // first-call timestamp.
            new ToolCall('Bash', arguments: ['command' => 'ls'], startedAtMs: 100.0),
            new ToolCall('symfony_logs', startedAtMs: 2200.0, mcp: true),
            new ToolCall('symfony_logs', errored: true, startedAtMs: 2400.0, mcp: true),
            new ToolCall('symfony_container', startedAtMs: 2310.0, mcp: true),
        ]);

        $metrics = $collector->collect($result, MateConfiguration::enabled(
            expectedTools: ['symfony_logs', 'symfony_profiler'],
        ));

        $this->assertTrue($metrics->enabled);
        $this->assertSame(3, $metrics->toolCallCount);
        $this->assertSame(['symfony_logs', 'symfony_container'], $metrics->toolNames);
        $this->assertSame(2200.0, $metrics->firstToolCallMs);
        $this->assertSame(1, $metrics->toolErrors);
        $this->assertSame(['symfony_profiler'], $metrics->missingExpectedTools);
    }

    public function testNonMcpCallsEarnNoMateCreditEvenWithMatchingName(): void
    {
        $collector = new MateMetricsCollector();
        $result = AssistantRunResult::success(stdout: '', durationMs: 0.0, toolCalls: [
            new ToolCall('monolog-tail', startedAtMs: 500.0),
        ]);

        $metrics = $collector->collect($result, MateConfiguration::enabled(
            expectedToolsAny: ['monolog-tail'],
        ));

        $this->assertSame(0, $metrics->toolCallCount);
        $this->assertSame([], $metrics->toolNames);
        $this->assertNull($metrics->firstToolCallMs);
        $this->assertFalse($metrics->anyToolMatched);
    }

    public function testEmptyToolCallsStillReportEnabled(): void
    {
        $collector = new MateMetricsCollector();
        $result = AssistantRunResult::success(stdout: '', durationMs: 0.0);

        $metrics = $collector->collect($result, MateConfiguration::enabled());

        $this->assertTrue($metrics->enabled);
        $this->assertSame(0, $metrics->toolCallCount);
        $this->assertSame([], $metrics->missingExpectedTools);
    }

    public function testAnyOfMatchDetectedWhenOneToolPresent(): void
    {
        $collector = new MateMetricsCollector();
        $result = AssistantRunResult::success(stdout: '', durationMs: 0.0, toolCalls: [
            new ToolCall('monolog-tail', mcp: true),
            new ToolCall('Read'),
        ]);

        $metrics = $collector->collect($result, MateConfiguration::enabled(
            expectedToolsAny: ['monolog-search', 'monolog-tail', 'monolog-list-files'],
        ));

        $this->assertTrue($metrics->anyToolMatched);
        $this->assertSame(['monolog-search', 'monolog-tail', 'monolog-list-files'], $metrics->expectedToolsAny);
    }

    public function testAnyOfNoMatchWhenNonePresent(): void
    {
        $collector = new MateMetricsCollector();
        $result = AssistantRunResult::success(stdout: '', durationMs: 0.0, toolCalls: [
            new ToolCall('Read'),
            new ToolCall('Bash'),
        ]);

        $metrics = $collector->collect($result, MateConfiguration::enabled(
            expectedToolsAny: ['monolog-search', 'monolog-tail'],
        ));

        $this->assertFalse($metrics->anyToolMatched);
    }
}
