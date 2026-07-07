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
 * Rule-based root-cause matcher.
 *
 * Searches the assistant stdout/stderr and the workspace diff for each phrase in
 * `expected.root_cause`. The score is proportional to the matched fraction.
 * The implementation is intentionally simple and can later be replaced or
 * complemented by an LLM-as-judge variant.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class RootCauseEvaluator implements EvaluatorInterface
{
    public const NAME = 'root_cause';

    public function name(): string
    {
        return self::NAME;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $expected = $input->scenario->expected['root_cause'] ?? [];
        $expected = \is_array($expected) ? array_values(array_filter($expected, 'is_string')) : [];

        if ([] === $expected) {
            return new EvaluationResult(
                name: self::NAME,
                score: 0.0,
                passed: false,
                explanation: 'Scenario does not declare root_cause keywords; cannot evaluate.',
                evidence: ['expected' => [], 'matched' => []],
            );
        }

        $haystack = $this->buildHaystack($input);
        $matched = [];
        $missing = [];

        foreach ($expected as $keyword) {
            if ($this->contains($haystack, $keyword)) {
                $matched[] = $keyword;
            } else {
                $missing[] = $keyword;
            }
        }

        $ratio = \count($matched) / \count($expected);
        $score = round($ratio * EvaluationResult::MAX_SCORE, 2);
        $passed = \count($matched) === \count($expected);

        return new EvaluationResult(
            name: self::NAME,
            score: $score,
            passed: $passed,
            explanation: \sprintf('%d/%d root-cause keywords matched.', \count($matched), \count($expected)),
            evidence: [
                'expected' => $expected,
                'matched' => $matched,
                'missing' => $missing,
            ],
        );
    }

    private function buildHaystack(EvaluationInput $input): string
    {
        $assistant = $input->outcome->assistantResult;
        $diff = $input->outcome->diff;

        return strtolower(implode("\n", array_filter([
            $assistant?->stdout ?? '',
            $assistant?->stderr ?? '',
            $diff?->diff ?? '',
        ])));
    }

    private function contains(string $haystack, string $needle): bool
    {
        $needle = trim(strtolower($needle));
        if ('' === $needle) {
            return false;
        }

        return str_contains($haystack, $needle);
    }
}
