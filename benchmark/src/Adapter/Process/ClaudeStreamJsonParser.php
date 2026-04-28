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
 * Parses the `claude --print --output-format=stream-json` JSONL stream.
 *
 * The format is a sequence of newline-delimited JSON events. We walk the
 * stream defensively because the Claude Code schema can drift across
 * releases:
 *   - `assistant` events carry tool_use blocks describing tool invocations.
 *   - `result` events carry the final usage totals.
 *
 * Anything we cannot recognise is ignored rather than aborted.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ClaudeStreamJsonParser implements AssistantOutputParserInterface
{
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

            $type = (string) ($event['type'] ?? '');

            if ('assistant' === $type) {
                $blocks = $event['message']['content'] ?? [];
                if (!\is_array($blocks)) {
                    continue;
                }

                foreach ($blocks as $block) {
                    if (!\is_array($block)) {
                        continue;
                    }

                    if ('tool_use' === ($block['type'] ?? null)) {
                        $arguments = \is_array($block['input'] ?? null) ? $block['input'] : [];
                        $toolCalls[] = new ToolCall(
                            name: (string) ($block['name'] ?? 'unknown'),
                            arguments: $arguments,
                            startedAtMs: $startedAtMs,
                        );
                        $startedAtMs += 1.0;
                    }
                }
            }

            if ('result' === $type) {
                $usage = $event['usage'] ?? null;
                if (\is_array($usage)) {
                    $tokens = new TokenUsage(
                        inputTokens: (int) ($usage['input_tokens'] ?? 0),
                        outputTokens: (int) ($usage['output_tokens'] ?? 0),
                        cachedTokens: (int) (($usage['cache_read_input_tokens'] ?? 0) + ($usage['cache_creation_input_tokens'] ?? 0)),
                    );
                }
            }
        }

        return new ParsedAssistantOutput(
            tokenUsage: $tokens,
            toolCalls: $toolCalls,
        );
    }
}
