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
 * Immutable handle for an isolated benchmark workspace on disk.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class Workspace
{
    public function __construct(
        public string $path,
        public string $runId,
        public string $scenarioId,
        public int $attempt,
        public bool $keep,
    ) {
    }
}
