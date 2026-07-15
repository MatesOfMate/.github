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
 * Outcome of one evaluator: a 0..5 score, a pass/fail flag, an explanation, and machine-readable evidence.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class EvaluationResult
{
    public const MIN_SCORE = 0.0;
    public const MAX_SCORE = 5.0;

    /**
     * @param array<string, mixed> $evidence
     */
    public function __construct(
        public string $name,
        public float $score,
        public bool $passed,
        public string $explanation,
        public array $evidence = [],
        public bool $applicable = true,
    ) {
        if ($score < self::MIN_SCORE || $score > self::MAX_SCORE) {
            throw new \InvalidArgumentException(\sprintf('Evaluator score must be within [%.1f, %.1f], got %.2f.', self::MIN_SCORE, self::MAX_SCORE, $score));
        }
    }

    /**
     * A category that cannot be judged for this run (e.g. Mate tool usage while
     * Mate is disabled). The {@see \MatesOfMate\Benchmark\Scoring\ScoreCalculator}
     * excludes it and renormalises the remaining weights instead of scoring 0,
     * so runs stay comparable across configurations.
     *
     * @param array<string, mixed> $evidence
     */
    public static function notApplicable(string $name, string $explanation, array $evidence = []): self
    {
        return new self(
            name: $name,
            score: 0.0,
            passed: false,
            explanation: $explanation,
            evidence: $evidence,
            applicable: false,
        );
    }
}
