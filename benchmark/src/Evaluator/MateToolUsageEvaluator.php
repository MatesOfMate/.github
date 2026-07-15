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
 * The category only applies when Mate is enabled *and* the scenario declares
 * expected Mate tools. Disabled runs, and enabled runs on scenarios with no
 * tool expectation, are marked not-applicable so they are excluded from the
 * weighted score (weights renormalise over the remaining categories). This
 * keeps Mate-on and Mate-off scores comparable on tasks that native tooling
 * already solves. When an expectation is declared, the score is proportional
 * to the share of expected tool names that actually appeared in the run.
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
            return EvaluationResult::notApplicable(
                self::NAME,
                'Mate is disabled for this run; category excluded and weights renormalised.',
                ['enabled' => false],
            );
        }

        // any-of check: full score when at least one of the declared alternatives was called.
        if ([] !== $mate->expectedToolsAny) {
            $matched = array_values(array_intersect($mate->expectedToolsAny, $mate->toolNames));
            $score = $mate->anyToolMatched ? EvaluationResult::MAX_SCORE : 0.0;

            return new EvaluationResult(
                name: self::NAME,
                score: $score,
                passed: $mate->anyToolMatched,
                explanation: $mate->anyToolMatched
                    ? \sprintf('Used at least one expected Mate tool: %s.', implode(', ', $matched))
                    : \sprintf('None of the expected Mate tools were used (needed any of: %s).', implode(', ', $mate->expectedToolsAny)),
                evidence: [
                    'enabled' => true,
                    'expected_any' => $mate->expectedToolsAny,
                    'matched' => $matched,
                    'tool_call_count' => $mate->toolCallCount,
                    'tool_names' => $mate->toolNames,
                ],
            );
        }

        $expected = $mate->expectedTools;
        if ([] === $expected) {
            // The scenario declares no Mate-tool expectation, so Mate-tool usage
            // is out of scope: a task solvable with native tools must not be
            // penalised for skipping Mate, nor credited for touching it. Marking
            // the category not-applicable keeps Mate-on and Mate-off scores
            // comparable on scenarios that do not measure Mate specifically.
            return EvaluationResult::notApplicable(
                self::NAME,
                \sprintf(
                    'Scenario declares no expected Mate tools; Mate-tool usage is out of scope (observed %d Mate call(s)). Category excluded and weights renormalised.',
                    $mate->toolCallCount,
                ),
                [
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
