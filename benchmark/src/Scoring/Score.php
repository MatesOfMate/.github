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

/**
 * Aggregated benchmark score with full visibility into per-category values and gating.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class Score
{
    public const MIN = 0.0;
    public const MAX = 5.0;

    /**
     * @param array<string, float|null> $perCategory       Score per category; null means no evaluator data
     * @param array<string, float>      $weights           Normalised weights actually used
     * @param list<string>              $missingEvaluators
     * @param array<string, float>      $gatePenalties     Multipliers applied due to gate-evaluator failures
     * @param list<string>              $notApplicable     Categories excluded from scoring for this run
     * @param array<string, float>      $effectiveWeights  Renormalised weights actually applied after exclusions
     */
    public function __construct(
        public float $finalScore,
        public float $rawScore,
        public array $perCategory,
        public array $weights,
        public array $missingEvaluators = [],
        public array $gatePenalties = [],
        public array $notApplicable = [],
        public array $effectiveWeights = [],
    ) {
    }

    public static function zero(): self
    {
        return new self(
            finalScore: 0.0,
            rawScore: 0.0,
            perCategory: [],
            weights: [],
        );
    }
}
