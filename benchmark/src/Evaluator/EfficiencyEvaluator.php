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
 * Scores wall-clock and (when available) token efficiency for the attempt.
 *
 * Thresholds are intentionally simple defaults; suites that need stricter
 * targets can swap this evaluator for a tuned implementation later.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class EfficiencyEvaluator implements EvaluatorInterface
{
    public const NAME = 'efficiency';

    public function __construct(
        private readonly float $excellentDurationMs = 30_000.0,
        private readonly float $goodDurationMs = 60_000.0,
        private readonly float $okDurationMs = 120_000.0,
        private readonly float $weakDurationMs = 300_000.0,
        private readonly int $excellentTokens = 5_000,
        private readonly int $goodTokens = 15_000,
        private readonly int $weakTokens = 50_000,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $duration = (float) ($input->outcome->metrics->get('duration_ms') ?? 0.0);
        $tokens = $input->outcome->metrics->get('total_tokens');

        $durationScore = $this->scoreDuration($duration);
        $tokenScore = $this->scoreTokens(\is_int($tokens) ? $tokens : null);

        $score = null !== $tokenScore ? round(($durationScore + $tokenScore) / 2, 2) : $durationScore;
        $passed = $score >= 4.0;

        $explanation = null !== $tokenScore
            ? \sprintf('Duration %.1fms (score %.1f), tokens %d (score %.1f).', $duration, $durationScore, (int) $tokens, $tokenScore)
            : \sprintf('Duration %.1fms (score %.1f); tokens not reported.', $duration, $durationScore);

        return new EvaluationResult(
            name: self::NAME,
            score: $score,
            passed: $passed,
            explanation: $explanation,
            evidence: [
                'duration_ms' => $duration,
                'duration_score' => $durationScore,
                'total_tokens' => $tokens,
                'token_score' => $tokenScore,
            ],
        );
    }

    private function scoreDuration(float $durationMs): float
    {
        if ($durationMs <= $this->excellentDurationMs) {
            return 5.0;
        }
        if ($durationMs <= $this->goodDurationMs) {
            return 4.0;
        }
        if ($durationMs <= $this->okDurationMs) {
            return 3.0;
        }
        if ($durationMs <= $this->weakDurationMs) {
            return 2.0;
        }

        return 1.0;
    }

    private function scoreTokens(?int $tokens): ?float
    {
        if (null === $tokens) {
            return null;
        }

        if ($tokens <= $this->excellentTokens) {
            return 5.0;
        }
        if ($tokens <= $this->goodTokens) {
            return 4.0;
        }
        if ($tokens <= $this->weakTokens) {
            return 3.0;
        }

        return 1.0;
    }
}
