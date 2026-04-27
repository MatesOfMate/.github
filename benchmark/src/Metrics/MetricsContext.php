<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Metrics;

use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Mate\MateMetrics;
use MatesOfMate\Benchmark\Runner\CommandResult;
use MatesOfMate\Benchmark\Runner\DiffResult;

/**
 * Read-only bundle of inputs used by metrics collectors.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class MetricsContext
{
    /**
     * @param list<CommandResult> $setupResults
     * @param list<CommandResult> $baselineResults
     * @param list<CommandResult> $verificationResults
     */
    public function __construct(
        public ?AssistantRunResult $assistantResult,
        public ?DiffResult $diff,
        public MateMetrics $mateMetrics,
        public array $setupResults,
        public array $baselineResults,
        public array $verificationResults,
        public float $totalDurationMs,
    ) {
    }
}
