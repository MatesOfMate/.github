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

use MatesOfMate\Benchmark\Adapter\TokenUsage;
use MatesOfMate\Benchmark\Adapter\ToolCall;

/**
 * Parses the JSONL stream emitted by `codex exec --json`.
 *
 * The Codex CLI surfaces nested events with a `msg.type` discriminator.
 * Recognised events include:
 *   - `tool_call` / `local_shell_call` / `function_call` — counted as tool calls.
 *   - `token_count` / `usage` — tallied as token usage.
 * Unknown shapes are skipped silently.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CodexJsonParser implements AssistantOutputParserInterface
{
    private const TOOL_TYPES = ['tool_call', 'local_shell_call', 'function_call', 'mcp_tool_call', 'apply_patch_call'];

    public function parse(string $stdout, string $stderr): ParsedAssistantOutput
    {
        $tokens = null;
        $toolCalls = [];
        $startedAtMs = 0.0;

        foreach (preg_split('/\R/', $stdout) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            try {
                $event = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (!\is_array($event)) {
                continue;
            }

            $msg = $event['msg'] ?? $event;
            if (!\is_array($msg)) {
                continue;
            }

            $type = (string) ($msg['type'] ?? '');

            if (\in_array($type, self::TOOL_TYPES, true)) {
                $name = (string) ($msg['name'] ?? $msg['tool_name'] ?? $msg['call_id'] ?? $type);
                $arguments = \is_array($msg['arguments'] ?? null) ? $msg['arguments'] : [];
                $toolCalls[] = new ToolCall(
                    name: $name,
                    arguments: $arguments,
                    startedAtMs: $startedAtMs,
                );
                $startedAtMs += 1.0;
            }

            if (\in_array($type, ['token_count', 'usage', 'task_complete'], true)) {
                $usage = $msg['info']['total_token_usage'] ?? $msg['usage'] ?? $msg;
                if (\is_array($usage)) {
                    $input = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
                    $output = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
                    $cached = (int) ($usage['cached_input_tokens'] ?? $usage['cache_read_input_tokens'] ?? 0);

                    if ($input > 0 || $output > 0) {
                        $tokens = new TokenUsage(
                            inputTokens: $input,
                            outputTokens: $output,
                            cachedTokens: $cached,
                        );
                    }
                }
            }
        }

        return new ParsedAssistantOutput(
            tokenUsage: $tokens,
            toolCalls: $toolCalls,
        );
    }
}
