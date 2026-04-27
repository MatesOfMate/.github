<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Evaluator;

/**
 * Scores how well the assistant exercised Mate tools relative to scenario expectations.
 *
 * Disabled Mate runs always score 0 (there is nothing to evaluate). Enabled
 * runs are scored proportionally to the share of expected tool names that
 * actually appeared in the run; if no expectation is declared, any tool call
 * counts as decent usage.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MateToolUsageEvaluator implements EvaluatorInterface
{
    public const NAME = 'mate_tool_usage';

    public function name(): string
    {
        return self::NAME;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $mate = $input->outcome->mateMetrics;

        if (!$mate->enabled) {
            return new EvaluationResult(
                name: self::NAME,
                score: 0.0,
                passed: false,
                explanation: 'Mate is disabled for this run; tool usage cannot be evaluated.',
                evidence: ['enabled' => false],
            );
        }

        $expected = $mate->expectedTools;
        if ([] === $expected) {
            $score = $mate->toolCallCount > 0 ? 4.0 : 1.0;

            return new EvaluationResult(
                name: self::NAME,
                score: $score,
                passed: $mate->toolCallCount > 0,
                explanation: $mate->toolCallCount > 0
                    ? \sprintf('Mate enabled with %d tool calls but no expected tools were declared.', $mate->toolCallCount)
                    : 'Mate enabled but no tool calls were observed.',
                evidence: [
                    'enabled' => true,
                    'tool_call_count' => $mate->toolCallCount,
                    'expected' => [],
                    'tool_names' => $mate->toolNames,
                ],
            );
        }

        $matchedCount = \count($expected) - \count($mate->missingExpectedTools);
        $ratio = $matchedCount / \count($expected);
        $score = round($ratio * EvaluationResult::MAX_SCORE, 2);

        return new EvaluationResult(
            name: self::NAME,
            score: $score,
            passed: [] === $mate->missingExpectedTools,
            explanation: \sprintf(
                '%d/%d expected Mate tools used; missing: %s.',
                $matchedCount,
                \count($expected),
                [] === $mate->missingExpectedTools ? 'none' : implode(', ', $mate->missingExpectedTools),
            ),
            evidence: [
                'enabled' => true,
                'expected' => $expected,
                'matched' => array_values(array_diff($expected, $mate->missingExpectedTools)),
                'missing' => $mate->missingExpectedTools,
                'tool_call_count' => $mate->toolCallCount,
                'tool_names' => $mate->toolNames,
            ],
        );
    }
}
