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

/**
 * Diff produced by an AI execution against the workspace baseline.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class DiffResult
{
    /**
     * @param list<string> $changedFiles
     */
    public function __construct(
        public string $diff,
        public string $stat,
        public array $changedFiles,
        public int $additions,
        public int $deletions,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->changedFiles;
    }
}
