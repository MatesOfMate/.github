<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Runner;

/**
 * Raw Rector process result.
 *
 * @internal
 *
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class RunResult
{
    /**
     * @param array<int, string> $command
     */
    public function __construct(
        public readonly array $command,
        public readonly string $strategy,
        public readonly string $workingDirectory,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
        public readonly bool $timedOut,
    ) {
    }
}
