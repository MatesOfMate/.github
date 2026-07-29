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

use MatesOfMate\RectorExtension\Discovery\ExecutionStrategy;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Executes Rector with explicit preview and apply modes.
 *
 * @internal
 *
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class RectorRunner
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly int $timeout = 300,
    ) {
    }

    public function preview(ExecutionStrategy $strategy, string $configuration, ?string $path = null, bool $debug = false, bool $rulesSummary = false): RunResult
    {
        return $this->run($strategy, $configuration, $path, true, $debug, $rulesSummary);
    }

    public function apply(ExecutionStrategy $strategy, string $configuration, ?string $path = null, bool $debug = false, bool $rulesSummary = false): RunResult
    {
        return $this->run($strategy, $configuration, $path, false, $debug, $rulesSummary);
    }

    /**
     * @return array<int, string>
     */
    public function buildCommand(
        ExecutionStrategy $strategy,
        string $configuration,
        ?string $path,
        bool $dryRun,
        bool $debug,
        bool $rulesSummary,
    ): array {
        $command = [
            ...$strategy->command,
            'process',
            '--config',
            $configuration,
        ];

        if ($dryRun) {
            $command[] = '--dry-run';
        }

        $command[] = '--no-progress-bar';
        $command[] = '--output-format=json';

        if ($debug) {
            $command[] = '--debug';
        }

        if ($rulesSummary) {
            $command[] = '--rules-summary';
        }

        if (null !== $path) {
            $command[] = $path;
        }

        return $command;
    }

    private function run(ExecutionStrategy $strategy, string $configuration, ?string $path, bool $dryRun, bool $debug, bool $rulesSummary): RunResult
    {
        $command = $this->buildCommand($strategy, $configuration, $path, $dryRun, $debug, $rulesSummary);
        $process = new Process($command, $this->projectRoot);
        $process->setTimeout($this->timeout);

        try {
            $process->run();

            return new RunResult(
                command: $command,
                strategy: $strategy->type,
                workingDirectory: $this->projectRoot,
                exitCode: $process->getExitCode() ?? 1,
                output: $process->getOutput(),
                errorOutput: $process->getErrorOutput(),
                timedOut: false,
            );
        } catch (ProcessTimedOutException $exception) {
            return new RunResult(
                command: $command,
                strategy: $strategy->type,
                workingDirectory: $this->projectRoot,
                exitCode: 124,
                output: $process->getOutput(),
                errorOutput: $exception->getMessage()."\n".$process->getErrorOutput(),
                timedOut: true,
            );
        }
    }
}
