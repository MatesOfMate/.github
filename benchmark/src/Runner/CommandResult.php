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
 * Captures the outcome of a single shell command run inside a workspace.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class CommandResult
{
    public function __construct(
        public string $command,
        public string $cwd,
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public float $durationMs,
        public bool $timedOut,
    ) {
    }

    public function successful(): bool
    {
        return 0 === $this->exitCode && !$this->timedOut;
    }
}
