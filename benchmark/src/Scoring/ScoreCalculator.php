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
use MatesOfMate\Benchmark\Evaluator\FunctionalEvaluator;
use MatesOfMate\Benchmark\Scenario\Scenario;

/**
 * Combines evaluator outputs into a single weighted {@see Score}.
 *
 * Categories whose evaluator reports `applicable = false` (or is missing) are
 * excluded and the remaining weights are renormalised, so a `--mate=disabled`
 * run can still reach 5.0 and stays comparable to a `--mate=enabled` run.
 *
 * Gate evaluators multiply the final score when they fail:
 * - `forbidden_changes` (default 0.0): touching protected files (e.g. the
 *   verification tests) invalidates the attempt outright.
 * - `functional` (default 0.25): quality categories (root cause, minimality,
 *   verification) are only worth a fraction when the fix does not actually
 *   work — a fast, plausible-sounding failure must score near zero.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScoreCalculator
{
    /**
     * @var array<string, float>
     */
    public const DEFAULT_GATES = [
        ForbiddenChangesEvaluator::NAME => 0.0,
        FunctionalEvaluator::NAME => 0.25,
    ];

    /**
     * @param array<string, float> $gateEvaluators evaluator name => multiplier applied to the final score when it fails
     */
    public function __construct(
        private readonly ScoreWeights $defaultWeights,
        private readonly array $gateEvaluators = self::DEFAULT_GATES,
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
        $missing = [];
        $notApplicable = [];
        $effectiveWeights = [];

        foreach ($weights->weights as $category => $weight) {
            if (!isset($byName[$category])) {
                $perCategory[$category] = null;
                $missing[] = $category;
                continue;
            }

            if (!$byName[$category]->applicable) {
                $perCategory[$category] = null;
                $notApplicable[] = $category;
                continue;
            }

            $perCategory[$category] = $byName[$category]->score;
            $effectiveWeights[$category] = $weight;
        }

        $weightSum = array_sum($effectiveWeights);
        $rawScore = 0.0;

        if ($weightSum > 0.0) {
            foreach ($effectiveWeights as $category => $weight) {
                $effectiveWeights[$category] = $weight / $weightSum;
                $rawScore += (float) $perCategory[$category] * $effectiveWeights[$category];
            }
        }

        $finalScore = $rawScore;
        $penalties = [];

        foreach ($this->gateEvaluators as $gate => $multiplier) {
            if (isset($byName[$gate]) && $byName[$gate]->applicable && !$byName[$gate]->passed) {
                $finalScore *= $multiplier;
                $penalties[$gate] = $multiplier;
            }
        }

        return new Score(
            finalScore: round(max(Score::MIN, min(Score::MAX, $finalScore)), 2),
            rawScore: round($rawScore, 2),
            perCategory: $perCategory,
            weights: $weights->weights,
            missingEvaluators: $missing,
            gatePenalties: $penalties,
            notApplicable: $notApplicable,
            effectiveWeights: array_map(static fn (float $weight): float => round($weight, 4), $effectiveWeights),
        );
    }
}
