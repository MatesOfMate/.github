<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Metrics\Collector;

use MatesOfMate\Benchmark\Metrics\MetricsCollectorInterface;
use MatesOfMate\Benchmark\Metrics\MetricsContext;

/**
 * Reports tool-call counts, names and timing both globally and for Mate-tagged calls.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ToolUsageCollector implements MetricsCollectorInterface
{
    public function collect(MetricsContext $context): array
    {
        $assistant = $context->assistantResult;
        $toolCalls = $assistant->toolCalls ?? [];
        $callCount = \count($toolCalls);

        $errors = 0;
        $firstCallMs = null;
        $names = [];

        foreach ($toolCalls as $call) {
            $names[] = $call->name;
            if ($call->errored) {
                ++$errors;
            }
            if (null !== $call->startedAtMs && (null === $firstCallMs || $call->startedAtMs < $firstCallMs)) {
                $firstCallMs = $call->startedAtMs;
            }
        }

        $uniqueNameCount = \count(array_unique($names));
        $redundant = $callCount - $uniqueNameCount;

        return [
            'tool_call_count' => $callCount,
            'tool_error_count' => $errors,
            'time_to_first_tool_call_ms' => $firstCallMs,
            'redundant_tool_call_count' => $callCount > 0 ? $redundant : 0,
            'mate_tool_call_count' => $context->mateMetrics->toolCallCount,
            'mate_tool_names' => $context->mateMetrics->toolNames,
            'first_mate_tool_call_ms' => $context->mateMetrics->firstToolCallMs,
        ];
    }
}
