<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Scoring;

use MatesOfMate\Benchmark\Evaluator\EvaluationResult;
use MatesOfMate\Benchmark\Evaluator\ForbiddenChangesEvaluator;
use MatesOfMate\Benchmark\Scenario\Scenario;

/**
 * Combines evaluator outputs into a single weighted {@see Score}.
 *
 * Gating evaluators (default: `forbidden_changes`) can apply a multiplier to
 * the final score when they fail; the raw weighted sum is preserved alongside
 * for reporting and debugging.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScoreCalculator
{
    /**
     * @param list<string> $gateEvaluators
     */
    public function __construct(
        private readonly ScoreWeights $defaultWeights,
        private readonly array $gateEvaluators = [ForbiddenChangesEvaluator::NAME],
        private readonly float $gateFailurePenalty = 0.5,
    ) {
    }

    public static function withDefaults(): self
    {
        return new self(ScoreWeights::defaults());
    }

    /**
     * @param list<EvaluationResult> $evaluations
     */
    public function calculate(Scenario $scenario, array $evaluations): Score
    {
        $weights = ScoreWeights::fromScenario($scenario, $this->defaultWeights);

        $byName = [];
        foreach ($evaluations as $evaluation) {
            $byName[$evaluation->name] = $evaluation;
        }

        $perCategory = [];
        $rawScore = 0.0;
        $missing = [];

        foreach ($weights->weights as $category => $weight) {
            if (isset($byName[$category])) {
                $perCategory[$category] = $byName[$category]->score;
                $rawScore += $byName[$category]->score * $weight;
            } else {
                $perCategory[$category] = null;
                $missing[] = $category;
            }
        }

        $finalScore = $rawScore;
        $penalties = [];

        foreach ($this->gateEvaluators as $gate) {
            if (isset($byName[$gate]) && !$byName[$gate]->passed) {
                $finalScore *= $this->gateFailurePenalty;
                $penalties[$gate] = $this->gateFailurePenalty;
            }
        }

        return new Score(
            finalScore: round(max(Score::MIN, min(Score::MAX, $finalScore)), 2),
            rawScore: round($rawScore, 2),
            perCategory: $perCategory,
            weights: $weights->weights,
            missingEvaluators: $missing,
            gatePenalties: $penalties,
        );
    }
}
