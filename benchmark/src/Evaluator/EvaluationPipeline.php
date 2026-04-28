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
 * Runs each registered evaluator against a single {@see EvaluationInput}.
 *
 * Evaluator exceptions are converted to failing {@see EvaluationResult}s so
 * one broken judge cannot crash the run.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class EvaluationPipeline
{
    /**
     * @var list<EvaluatorInterface>
     */
    private array $evaluators;

    /**
     * @param iterable<EvaluatorInterface>|null $evaluators
     */
    public function __construct(?iterable $evaluators = null)
    {
        if (null === $evaluators) {
            $this->evaluators = self::defaultEvaluators();

            return;
        }

        $list = [];
        foreach ($evaluators as $evaluator) {
            $list[] = $evaluator;
        }
        $this->evaluators = $list;
    }

    /**
     * @return list<EvaluationResult>
     */
    public function evaluate(EvaluationInput $input): array
    {
        $results = [];

        foreach ($this->evaluators as $evaluator) {
            try {
                $results[] = $evaluator->evaluate($input);
            } catch (\Throwable $exception) {
                $results[] = new EvaluationResult(
                    name: $evaluator->name(),
                    score: 0.0,
                    passed: false,
                    explanation: \sprintf('Evaluator threw: %s', $exception->getMessage()),
                    evidence: ['error' => $exception->getMessage()],
                );
            }
        }

        return $results;
    }

    /**
     * @return list<EvaluatorInterface>
     */
    public static function defaultEvaluators(): array
    {
        return [
            new FunctionalEvaluator(),
            new RootCauseEvaluator(),
            new MateToolUsageEvaluator(),
            new DiffMinimalityEvaluator(),
            new ForbiddenChangesEvaluator(),
            new VerificationEvaluator(),
            new EfficiencyEvaluator(),
        ];
    }
}
