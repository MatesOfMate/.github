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
 * Hard fail when the assistant touches files declared as `forbidden_files_changed`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ForbiddenChangesEvaluator implements EvaluatorInterface
{
    public const NAME = 'forbidden_changes';

    public function name(): string
    {
        return self::NAME;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $forbidden = $input->scenario->expected['forbidden_files_changed'] ?? [];
        $forbidden = \is_array($forbidden) ? array_values(array_filter($forbidden, 'is_string')) : [];

        $diff = $input->outcome->diff;
        $changed = null !== $diff ? $diff->changedFiles : [];

        $violations = [];
        foreach ($changed as $path) {
            foreach ($forbidden as $rule) {
                if ('' !== $rule && str_contains($path, $rule)) {
                    $violations[] = $path;
                    break;
                }
            }
        }

        if ([] === $violations) {
            return new EvaluationResult(
                name: self::NAME,
                score: 5.0,
                passed: true,
                explanation: [] === $forbidden
                    ? 'No forbidden_files_changed declared.'
                    : 'No forbidden files changed.',
                evidence: ['forbidden' => $forbidden, 'violations' => []],
            );
        }

        return new EvaluationResult(
            name: self::NAME,
            score: 0.0,
            passed: false,
            explanation: \sprintf('Changed %d forbidden file(s).', \count($violations)),
            evidence: ['forbidden' => $forbidden, 'violations' => $violations],
        );
    }
}
