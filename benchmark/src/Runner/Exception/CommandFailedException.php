<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Runner\Exception;

use MatesOfMate\Benchmark\Runner\CommandResult;

/**
 * Thrown when a command that must succeed (e.g. setup, git plumbing) returns a non-zero exit.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CommandFailedException extends \RuntimeException
{
    public function __construct(
        public readonly CommandResult $result,
        ?string $stage = null,
    ) {
        $message = \sprintf(
            "%s command failed with exit code %d in %s\n  command: %s\n  stderr: %s",
            $stage ?? 'Setup',
            $result->exitCode,
            $result->cwd,
            $result->command,
            trim($result->stderr) ?: trim($result->stdout),
        );

        parent::__construct($message);
    }
}
