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
        $expectedFiles = $this->expectedFiles($input);

        if (!$diff instanceof \MatesOfMate\Benchmark\Runner\DiffResult) {
            return new EvaluationResult(
                name: self::NAME,
                score: 0.0,
                passed: false,
                explanation: 'No diff captured; cannot evaluate minimality.',
                evidence: ['files_changed' => null, 'expected_files' => $expectedFiles],
            );
        }

        $actualFiles = array_values(array_unique(array_map(
            static fn (string $file): string => ltrim(trim($file), './'),
            $diff->changedFiles,
        )));

        if ([] === $actualFiles) {
            return new EvaluationResult(
                name: self::NAME,
                score: 0.0,
                passed: false,
                explanation: 'Diff is empty; assistant did not change anything.',
                evidence: ['files_changed' => 0, 'expected_files' => $expectedFiles],
            );
        }

        if ([] === $expectedFiles) {
            // No declared expectation: reward focused diffs by file count only.
            $score = match (true) {
                \count($actualFiles) <= 1 => 5.0,
                \count($actualFiles) <= 2 => 4.0,
                \count($actualFiles) <= 4 => 3.0,
                \count($actualFiles) <= 8 => 2.0,
                default => 1.0,
            };

            return new EvaluationResult(
                name: self::NAME,
                score: $score,
                passed: $score >= 4.0,
                explanation: \sprintf('Changed %d file(s); no expected file set declared.', \count($actualFiles)),
                evidence: [
                    'files_changed' => \count($actualFiles),
                    'expected_files' => [],
                    'additions' => $diff->additions,
                    'deletions' => $diff->deletions,
                ],
            );
        }

        // Compare the actual file set against the expected one: touching
        // exactly the expected files is a 5; both extra files and missed
        // expected files reduce the overlap (Jaccard similarity).
        $intersection = array_values(array_intersect($expectedFiles, $actualFiles));
        $union = array_values(array_unique([...$expectedFiles, ...$actualFiles]));
        $missing = array_values(array_diff($expectedFiles, $actualFiles));
        $extra = array_values(array_diff($actualFiles, $expectedFiles));

        $similarity = \count($intersection) / max(\count($union), 1);
        $score = round($similarity * EvaluationResult::MAX_SCORE, 2);
        $passed = [] === $missing && [] === $extra;

        return new EvaluationResult(
            name: self::NAME,
            score: $score,
            passed: $passed,
            explanation: \sprintf(
                'Changed files overlap %d/%d with expectation (%d missing, %d extra).',
                \count($intersection),
                \count($union),
                \count($missing),
                \count($extra),
            ),
            evidence: [
                'files_changed' => \count($actualFiles),
                'expected_files' => $expectedFiles,
                'missing_expected' => $missing,
                'extra_files' => $extra,
                'additions' => $diff->additions,
                'deletions' => $diff->deletions,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function expectedFiles(EvaluationInput $input): array
    {
        $raw = $input->scenario->expected['expected_files_changed'] ?? [];
        if (!\is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($file): string => \is_string($file) ? ltrim(trim($file), './') : '', $raw),
            static fn (string $file): bool => '' !== $file,
        )));
    }
}
