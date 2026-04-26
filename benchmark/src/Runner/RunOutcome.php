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
use MatesOfMate\Benchmark\Scenario\Scenario;

/**
 * Aggregated record of one scenario attempt.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class RunOutcome
{
    /**
     * @param list<CommandResult> $setupResults
     * @param list<CommandResult> $baselineResults
     * @param list<CommandResult> $verificationResults
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
        public float $totalDurationMs,
        public ?string $errorMessage = null,
    ) {
    }
}
