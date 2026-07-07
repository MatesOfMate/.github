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

use MatesOfMate\Benchmark\Runner\Exception\CommandFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Executes shell commands inside a workspace and captures the outcome.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CommandExecutor
{
    public const DEFAULT_TIMEOUT_SECONDS = 300;

    /**
     * Runs a shell-style command and returns a {@see CommandResult}.
     *
     * Never throws on a non-zero exit code; callers can decide whether to enforce success.
     *
     * @param array<string, string> $env
     */
    public function execute(string $command, string $cwd, int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS, array $env = []): CommandResult
    {
        $process = Process::fromShellCommandline($command, $cwd, $env, null, $timeoutSeconds);

        $start = microtime(true);
        $timedOut = false;

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        }

        $durationMs = (microtime(true) - $start) * 1000.0;

        return new CommandResult(
            command: $command,
            cwd: $cwd,
            exitCode: $process->getExitCode() ?? -1,
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
            durationMs: $durationMs,
            timedOut: $timedOut,
        );
    }

    /**
     * Runs a command and throws if it fails, used for plumbing that must succeed (setup, git).
     *
     * @param array<string, string> $env
     */
    public function mustExecute(string $command, string $cwd, ?string $stage = null, int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS, array $env = []): CommandResult
    {
        $result = $this->execute($command, $cwd, $timeoutSeconds, $env);

        if (!$result->successful()) {
            throw new CommandFailedException($result, $stage);
        }

        return $result;
    }
}
