<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Adapter\Process;

use MatesOfMate\Benchmark\Adapter\AssistantAdapterInterface;
use MatesOfMate\Benchmark\Adapter\AssistantRunInput;
use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Base class for adapters that wrap a CLI binary as a subprocess.
 *
 * Concrete adapters provide the binary name and the argument list for a given
 * input; this base handles process spawning, stdin, env merging, output
 * capture, parser invocation, and timeout handling.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
abstract class ProcessAdapter implements AssistantAdapterInterface
{
    public function __construct(
        protected readonly string $binary,
        protected readonly AssistantOutputParserInterface $parser,
    ) {
    }

    public function run(AssistantRunInput $input): AssistantRunResult
    {
        if ('' === $this->binary) {
            return AssistantRunResult::failure(errorMessage: 'Adapter binary is not configured.');
        }

        $command = $this->buildCommand($input);
        $env = $this->buildEnv($input);

        $process = Process::fromShellCommandline(
            $command,
            $input->workspacePath,
            $env,
            $this->buildStdin($input),
            $input->timeoutSeconds,
        );

        $start = microtime(true);
        $timedOut = false;

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        } catch (\Throwable $exception) {
            return AssistantRunResult::failure(
                errorMessage: \sprintf('Adapter failed to run: %s', $exception->getMessage()),
                durationMs: (microtime(true) - $start) * 1000.0,
            );
        }

        $durationMs = (microtime(true) - $start) * 1000.0;
        $exitCode = $process->getExitCode() ?? -1;
        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();

        $parsed = $this->parser->parse($stdout, $stderr);

        if ($timedOut) {
            return AssistantRunResult::failure(
                errorMessage: \sprintf('Adapter "%s" timed out after %d seconds.', $this->name(), $input->timeoutSeconds),
                durationMs: $durationMs,
                exitCode: $exitCode,
                stdout: $stdout,
                stderr: $stderr,
                timedOut: true,
                toolCalls: $parsed->toolCalls,
            );
        }

        if (0 !== $exitCode) {
            return AssistantRunResult::failure(
                errorMessage: \sprintf('Adapter "%s" exited with code %d.', $this->name(), $exitCode),
                durationMs: $durationMs,
                exitCode: $exitCode,
                stdout: $stdout,
                stderr: $stderr,
                toolCalls: $parsed->toolCalls,
            );
        }

        return new AssistantRunResult(
            successful: true,
            stdout: $stdout,
            stderr: $stderr,
            exitCode: 0,
            durationMs: $durationMs,
            tokenUsage: $parsed->tokenUsage,
            toolCalls: $parsed->toolCalls,
        );
    }

    /**
     * Build the full shell command line. Implementations should escape arguments
     * with {@see escapeshellarg()}.
     */
    abstract protected function buildCommand(AssistantRunInput $input): string;

    /**
     * Defaults to piping the prompt to the process via stdin. Subclasses can
     * override when their CLI prefers a different transport.
     */
    protected function buildStdin(AssistantRunInput $input): ?string
    {
        return $input->prompt;
    }

    /**
     * Merge inherited shell env with anything coming from the input.
     *
     * @return array<string, string>
     */
    protected function buildEnv(AssistantRunInput $input): array
    {
        $inherited = [];
        foreach (getenv() as $key => $value) {
            if (\is_string($key) && \is_string($value)) {
                $inherited[$key] = $value;
            }
        }

        return array_merge($inherited, $input->env);
    }
}
