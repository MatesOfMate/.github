<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Runner;

use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Evaluator\EvaluationResult;
use MatesOfMate\Benchmark\Mate\MateMetrics;
use MatesOfMate\Benchmark\Metrics\MetricsBag;
use MatesOfMate\Benchmark\Scenario\Scenario;
use MatesOfMate\Benchmark\Scoring\Score;

/**
 * Aggregated record of one scenario attempt.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class RunOutcome
{
    /**
     * @param list<CommandResult>      $setupResults
     * @param list<CommandResult>      $baselineResults
     * @param list<CommandResult>      $verificationResults
     * @param list<EvaluationResult>   $evaluations
     */
    public function __construct(
        public Scenario $scenario,
        public Workspace $workspace,
        public RunStatus $status,
        public array $setupResults,
        public array $baselineResults,
        public ?AssistantRunResult $assistantResult,
        public ?DiffResult $diff,
        public array $verificationResults,
        public MateMetrics $mateMetrics,
        public MetricsBag $metrics,
        public float $totalDurationMs,
        public ?string $errorMessage = null,
        public array $evaluations = [],
        public Score $score = new Score(0.0, 0.0, [], []),
    ) {
    }

    /**
     * @param list<EvaluationResult> $evaluations
     */
    public function withEvaluations(array $evaluations, Score $score): self
    {
        return new self(
            scenario: $this->scenario,
            workspace: $this->workspace,
            status: $this->status,
            setupResults: $this->setupResults,
            baselineResults: $this->baselineResults,
            assistantResult: $this->assistantResult,
            diff: $this->diff,
            verificationResults: $this->verificationResults,
            mateMetrics: $this->mateMetrics,
            metrics: $this->metrics,
            totalDurationMs: $this->totalDurationMs,
            errorMessage: $this->errorMessage,
            evaluations: $evaluations,
            score: $score,
        );
    }
}
