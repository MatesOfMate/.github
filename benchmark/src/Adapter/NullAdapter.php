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
 * Deterministic no-op adapter used to wire up and exercise the runner without spending model time.
 *
 * It does not modify the workspace, reports zero token usage, and emits no tool calls.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class NullAdapter implements AssistantAdapterInterface
{
    public const NAME = 'null';

    public function name(): string
    {
        return self::NAME;
    }

    public function run(AssistantRunInput $input): AssistantRunResult
    {
        $start = microtime(true);
        $stdout = \sprintf(
            "NullAdapter run\n  workspace: %s\n  model: %s\n  mate: %s\n  prompt-bytes: %d\n",
            $input->workspacePath,
            $input->model ?? '(none)',
            $input->isMateEnabled() ? 'enabled' : 'disabled',
            \strlen($input->prompt),
        );
        $duration = (microtime(true) - $start) * 1000.0;

        return AssistantRunResult::success(
            stdout: $stdout,
            durationMs: $duration,
            tokenUsage: null,
            toolCalls: [],
        );
    }
}
