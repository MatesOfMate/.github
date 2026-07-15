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
 * Scores how cheaply the assistant *succeeded* — wall-clock of the assistant
 * run itself plus fresh (non-cached) token consumption.
 *
 * Efficiency is only meaningful for functionally successful runs: a fast
 * no-op is not efficient, it is a failure. When the verification commands did
 * not pass this category reports not-applicable and its weight is
 * redistributed. Cached prompt tokens are excluded — cache reads are how CLI
 * agents are supposed to work and cost a fraction of fresh tokens.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class EfficiencyEvaluator implements EvaluatorInterface
{
    public const NAME = 'efficiency';

    public function __construct(
        private readonly float $excellentDurationMs = 60_000.0,
        private readonly float $goodDurationMs = 120_000.0,
        private readonly float $okDurationMs = 240_000.0,
        private readonly float $weakDurationMs = 480_000.0,
        private readonly int $excellentTokens = 5_000,
        private readonly int $goodTokens = 15_000,
        private readonly int $okTokens = 50_000,
        private readonly int $weakTokens = 150_000,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        if (!$this->functionalSuccess($input)) {
            return EvaluationResult::notApplicable(
                self::NAME,
                'Efficiency is only scored for functionally successful runs.',
                ['functional_success' => false],
            );
        }

        $assistant = $input->outcome->assistantResult;
        $duration = $assistant->durationMs ?? (float) ($input->outcome->metrics->get('duration_ms') ?? 0.0);
        $freshTokens = $this->freshTokens($input);

        $durationScore = $this->scoreDuration($duration);
        $tokenScore = $this->scoreTokens($freshTokens);

        $score = null !== $tokenScore ? round(($durationScore + $tokenScore) / 2, 2) : $durationScore;
        $passed = $score >= 4.0;

        $explanation = null !== $tokenScore
            ? \sprintf('Assistant duration %.1fs (score %.1f), fresh tokens %d (score %.1f).', $duration / 1000.0, $durationScore, (int) $freshTokens, $tokenScore)
            : \sprintf('Assistant duration %.1fs (score %.1f); tokens not reported.', $duration / 1000.0, $durationScore);

        return new EvaluationResult(
            name: self::NAME,
            score: $score,
            passed: $passed,
            explanation: $explanation,
            evidence: [
                'assistant_duration_ms' => $duration,
                'duration_score' => $durationScore,
                'fresh_tokens' => $freshTokens,
                'token_score' => $tokenScore,
                'cached_tokens' => $input->outcome->metrics->get('cached_tokens'),
            ],
        );
    }

    private function functionalSuccess(EvaluationInput $input): bool
    {
        $results = $input->outcome->verificationResults;
        if ([] === $results) {
            return false;
        }

        foreach ($results as $result) {
            if (!$result->successful()) {
                return false;
            }
        }

        return true;
    }

    private function freshTokens(EvaluationInput $input): ?int
    {
        $usage = $input->outcome->assistantResult?->tokenUsage;
        if (!$usage instanceof \MatesOfMate\Benchmark\Adapter\TokenUsage) {
            return null;
        }

        return $usage->inputTokens + $usage->outputTokens;
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
        if ($tokens <= $this->okTokens) {
            return 3.0;
        }
        if ($tokens <= $this->weakTokens) {
            return 2.0;
        }

        return 1.0;
    }
}
