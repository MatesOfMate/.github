<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Report;

use MatesOfMate\Benchmark\Runner\RunOutcome;

/**
 * Bundle of run-level metadata and per-attempt outcomes that report writers consume.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class ReportContext
{
    /**
     * @param list<RunOutcome> $outcomes
     */
    public function __construct(
        public string $runId,
        public string $reportDirectory,
        public string $adapter,
        public bool $mateEnabled,
        public ?string $model,
        public int $repeat,
        public array $outcomes,
        public \DateTimeImmutable $startedAt,
        public \DateTimeImmutable $finishedAt,
    ) {
    }

    public function durationSeconds(): float
    {
        return (float) ($this->finishedAt->getTimestamp() - $this->startedAt->getTimestamp());
    }
}
