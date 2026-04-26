<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Adapter;

/**
 * Outcome of a single adapter run.
 *
 * Adapter failures (process errors, timeouts, internal exceptions) are encoded
 * as instances of this class via {@see failure()} so they can be aggregated
 * into the benchmark report instead of crashing the runner.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class AssistantRunResult
{
    /**
     * @param list<ToolCall> $toolCalls
     */
    public function __construct(
        public bool $successful,
        public string $stdout,
        public string $stderr,
        public int $exitCode,
        public float $durationMs,
        public ?TokenUsage $tokenUsage,
        public array $toolCalls,
        public bool $timedOut = false,
        public ?string $errorMessage = null,
    ) {
    }

    /**
     * @param list<ToolCall> $toolCalls
     */
    public static function success(
        string $stdout,
        float $durationMs,
        ?TokenUsage $tokenUsage = null,
        array $toolCalls = [],
        string $stderr = '',
    ): self {
        return new self(
            successful: true,
            stdout: $stdout,
            stderr: $stderr,
            exitCode: 0,
            durationMs: $durationMs,
            tokenUsage: $tokenUsage,
            toolCalls: $toolCalls,
        );
    }

    /**
     * @param list<ToolCall> $toolCalls
     */
    public static function failure(
        string $errorMessage,
        float $durationMs = 0.0,
        int $exitCode = -1,
        string $stdout = '',
        string $stderr = '',
        bool $timedOut = false,
        array $toolCalls = [],
    ): self {
        return new self(
            successful: false,
            stdout: $stdout,
            stderr: $stderr,
            exitCode: $exitCode,
            durationMs: $durationMs,
            tokenUsage: null,
            toolCalls: $toolCalls,
            timedOut: $timedOut,
            errorMessage: $errorMessage,
        );
    }
}
