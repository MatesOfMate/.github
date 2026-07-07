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
 * Scores how many of `expected.pass_commands` actually pass after the assistant ran.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class FunctionalEvaluator implements EvaluatorInterface
{
    public const NAME = 'functional';

    public function name(): string
    {
        return self::NAME;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $results = $input->outcome->verificationResults;
        $total = \count($results);

        if (0 === $total) {
            return new EvaluationResult(
                name: self::NAME,
                score: 0.0,
                passed: false,
                explanation: 'No pass_commands defined; nothing to verify functionally.',
                evidence: ['total' => 0, 'passed' => 0, 'failed' => 0],
            );
        }

        $passed = 0;
        $failed = 0;
        foreach ($results as $result) {
            if ($result->successful()) {
                ++$passed;
            } else {
                ++$failed;
            }
        }

        $ratio = $passed / $total;
        $score = round($ratio * EvaluationResult::MAX_SCORE, 2);
        $allPassed = $passed === $total;

        return new EvaluationResult(
            name: self::NAME,
            score: $score,
            passed: $allPassed,
            explanation: \sprintf('%d/%d pass_commands succeeded.', $passed, $total),
            evidence: ['total' => $total, 'passed' => $passed, 'failed' => $failed],
        );
    }
}
