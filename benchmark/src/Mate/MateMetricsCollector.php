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

use MatesOfMate\Benchmark\Adapter\AssistantRunResult;

/**
 * Reduces the tool-call list of an {@see AssistantRunResult} into a {@see MateMetrics} record.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MateMetricsCollector
{
    public function collect(AssistantRunResult $result, MateConfiguration $configuration): MateMetrics
    {
        if (!$configuration->enabled) {
            return MateMetrics::disabled();
        }

        $names = [];
        $errors = 0;
        $firstCallMs = null;

        foreach ($result->toolCalls as $call) {
            $names[] = $call->name;

            if ($call->errored) {
                ++$errors;
            }

            if (null !== $call->startedAtMs && (null === $firstCallMs || $call->startedAtMs < $firstCallMs)) {
                $firstCallMs = $call->startedAtMs;
            }
        }

        $expected = $configuration->expectedTools;
        $missing = [] === $expected ? [] : array_values(array_diff($expected, $names));

        $expectedAny = $configuration->expectedToolsAny;
        $anyMatched = [] !== $expectedAny && [] !== array_intersect($expectedAny, $names);

        return new MateMetrics(
            enabled: true,
            toolCallCount: \count($result->toolCalls),
            toolNames: array_values(array_unique($names)),
            firstToolCallMs: $firstCallMs,
            toolErrors: $errors,
            expectedTools: $expected,
            missingExpectedTools: $missing,
            expectedToolsAny: $expectedAny,
            anyToolMatched: $anyMatched,
        );
    }
}
