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
 * Rewards small, focused diffs and penalises sprawling rewrites.
 *
 * The score is computed from the ratio between the actual number of changed
 * files and the count declared in `expected.expected_files_changed` (default 1
 * if no expectation is given).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class DiffMinimalityEvaluator implements EvaluatorInterface
{
    public const NAME = 'minimality';

    public function name(): string
    {
        return self::NAME;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $diff = $input->outcome->diff;
        $expected = $input->scenario->expected['expected_files_changed'] ?? [];
        $expectedCount = \is_array($expected) ? \count($expected) : 0;

        if (null === $diff) {
            return new EvaluationResult(
                name: self::NAME,
                score: 0.0,
                passed: false,
                explanation: 'No diff captured; cannot evaluate minimality.',
                evidence: ['files_changed' => null, 'expected_files' => $expectedCount],
            );
        }

        $actual = \count($diff->changedFiles);
        if (0 === $actual) {
            return new EvaluationResult(
                name: self::NAME,
                score: 0.0,
                passed: false,
                explanation: 'Diff is empty; assistant did not change anything.',
                evidence: ['files_changed' => 0, 'expected_files' => $expectedCount],
            );
        }

        $baseline = max($expectedCount, 1);
        $ratio = $actual / $baseline;

        if ($ratio <= 1.0) {
            $score = 5.0;
        } elseif ($ratio <= 1.5) {
            $score = 4.0;
        } elseif ($ratio <= 2.0) {
            $score = 3.0;
        } elseif ($ratio <= 3.0) {
            $score = 2.0;
        } elseif ($ratio <= 5.0) {
            $score = 1.0;
        } else {
            $score = 0.5;
        }

        return new EvaluationResult(
            name: self::NAME,
            score: $score,
            passed: $score >= 4.0,
            explanation: \sprintf('Changed %d file(s) vs %d expected (ratio %.2f).', $actual, $expectedCount, $ratio),
            evidence: [
                'files_changed' => $actual,
                'expected_files' => $expectedCount,
                'additions' => $diff->additions,
                'deletions' => $diff->deletions,
            ],
        );
    }
}
