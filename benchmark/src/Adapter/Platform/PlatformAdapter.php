<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Adapter\Platform;

use MatesOfMate\Benchmark\Adapter\AssistantAdapterInterface;
use MatesOfMate\Benchmark\Adapter\AssistantRunInput;
use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Adapter\TokenUsage;
use MatesOfMate\Benchmark\Adapter\ToolCall;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall as PlatformToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * Adapter base that delegates assistant execution to a {@see PlatformInterface}
 * from `symfony/ai-platform` (Claude Code or Codex bridges, etc.).
 *
 * The platform manages its own subprocess and stream-json parsing, so we only
 * marshal scenario inputs into `Platform::invoke()` arguments and convert the
 * returned result back into the benchmark's {@see AssistantRunResult}.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
abstract class PlatformAdapter implements AssistantAdapterInterface
{
    private const string TOOL_CALL_TRACES = 'tool_call_traces';

    public function __construct(
        protected readonly PlatformInterface $platform,
        protected readonly string $defaultModel,
    ) {
    }

    public function run(AssistantRunInput $input): AssistantRunResult
    {
        $start = microtime(true);

        try {
            $deferred = $this->platform->invoke(
                $this->resolveModel($input),
                $input->prompt,
                $this->buildOptions($input),
            );
            $result = $deferred->getResult();
        } catch (\Throwable $exception) {
            return AssistantRunResult::failure(
                errorMessage: $exception->getMessage(),
                durationMs: (microtime(true) - $start) * 1000.0,
            );
        }

        $durationMs = (microtime(true) - $start) * 1000.0;
        $stdout = $this->extractText($result);
        $tokens = $this->extractTokens($result);
        $toolCalls = $this->extractToolCalls($result);

        return AssistantRunResult::success(
            stdout: $stdout,
            durationMs: $durationMs,
            tokenUsage: $tokens,
            toolCalls: $toolCalls,
        );
    }

    /**
     * Pick the model name passed to the platform for the given run.
     *
     * @return non-empty-string
     */
    protected function resolveModel(AssistantRunInput $input): string
    {
        $model = $input->model ?? $this->defaultModel;

        if ('' === $model) {
            throw new \InvalidArgumentException('Model name must not be empty.');
        }

        return $model;
    }

    /**
     * Build the option array forwarded to {@see PlatformInterface::invoke()}.
     *
     * Subclasses can extend this to add bridge-specific flags.
     *
     * @return array<string, mixed>
     */
    protected function buildOptions(AssistantRunInput $input): array
    {
        $options = [
            'cwd' => $input->workspacePath,
        ];

        $mate = $input->mateConfig;
        if ($mate->enabled && null !== $mate->configPath) {
            $options['mcp_config'] = $mate->configPath;
        }

        return $options;
    }

    private function extractText(ResultInterface $result): string
    {
        // The Claude Code / Codex bridges return a MultiPartResult
        // (ToolCallResult parts + a final TextResult) whenever the CLI made
        // tool calls. Concatenate only the text parts.
        if ($result instanceof MultiPartResult) {
            return $result->asText();
        }

        $content = $result->getContent();

        if (\is_string($content)) {
            return $content;
        }

        if (is_iterable($content)) {
            $buffer = '';
            foreach ($content as $chunk) {
                $buffer .= \is_object($chunk) && method_exists($chunk, '__toString')
                    ? (string) $chunk
                    : (\is_scalar($chunk) ? (string) $chunk : '');
            }

            return $buffer;
        }

        if (\is_object($content) && method_exists($content, '__toString')) {
            return (string) $content;
        }

        return '';
    }

    private function extractTokens(ResultInterface $result): ?TokenUsage
    {
        $rawResult = $result->getRawResult();
        if (!$rawResult instanceof \Symfony\AI\Platform\Result\RawResultInterface) {
            return null;
        }

        $data = $rawResult->getData();

        $usage = $data['usage'] ?? null;
        if (!\is_array($usage)) {
            return null;
        }

        $input = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);

        // Claude-style: input_tokens already excludes cache traffic, which is
        // reported separately. Codex/OpenAI-style: cached_input_tokens is a
        // subset of input_tokens, so subtract it to get fresh input.
        $claudeCache = (int) (($usage['cache_read_input_tokens'] ?? 0) + ($usage['cache_creation_input_tokens'] ?? 0));
        $openAiCache = (int) ($usage['cached_input_tokens'] ?? ($usage['input_tokens_details']['cached_tokens'] ?? 0));

        $cached = $claudeCache + $openAiCache;
        if ($openAiCache > 0) {
            $input = max(0, $input - $openAiCache);
        }

        if (0 === $input && 0 === $output && 0 === $cached) {
            return null;
        }

        $cost = $data['total_cost_usd'] ?? null;

        return new TokenUsage(
            inputTokens: $input,
            outputTokens: $output,
            cachedTokens: $cached,
            costUsd: is_numeric($cost) ? (float) $cost : null,
        );
    }

    /**
     * @return list<ToolCall>
     */
    private function extractToolCalls(ResultInterface $result): array
    {
        $toolCalls = [];

        // Primary source: ToolCallResult parts inside the bridge's
        // MultiPartResult (aggregated from the CLI's stream-json events).
        if ($result instanceof MultiPartResult) {
            foreach ($result as $part) {
                if (!$part instanceof ToolCallResult) {
                    continue;
                }

                foreach ($part->getContent() as $platformCall) {
                    if (!$platformCall instanceof PlatformToolCall) {
                        continue;
                    }
                    if ('' === $platformCall->getName()) {
                        continue;
                    }
                    $name = $platformCall->getName();
                    $stripped = $this->stripMcpPrefix($name);

                    $toolCalls[] = new ToolCall(
                        name: $stripped,
                        arguments: $platformCall->getArguments(),
                        mcp: $stripped !== $name,
                    );
                }
            }
        }

        // Secondary source: adapter-specific trace metadata (kept for bridges
        // that expose richer per-call timing instead of result parts).
        $traces = $result->getMetadata()->get(self::TOOL_CALL_TRACES);
        if (!is_iterable($traces)) {
            return $toolCalls;
        }

        foreach ($traces as $trace) {
            $normalized = $this->normalizeToolCallTrace($trace);
            if (null === $normalized) {
                continue;
            }

            $toolCalls[] = new ToolCall(
                name: $normalized['name'],
                arguments: $normalized['arguments'],
                durationMs: $normalized['duration_ms'],
                errored: $normalized['errored'],
                startedAtMs: $normalized['started_at_ms'],
                mcp: $normalized['mcp'],
            );
        }

        return $toolCalls;
    }

    /**
     * @return array{
     *     name: string,
     *     arguments: array<string, mixed>,
     *     started_at_ms: ?float,
     *     duration_ms: ?float,
     *     errored: bool,
     *     mcp: bool
     * }|null
     */
    private function normalizeToolCallTrace(mixed $trace): ?array
    {
        if (\is_array($trace)) {
            $name = $trace['name'] ?? null;
            if (!\is_string($name) || '' === $name) {
                return null;
            }

            $stripped = $this->stripMcpPrefix($name);

            return [
                'name' => $stripped,
                'arguments' => \is_array($trace['arguments'] ?? null) ? $trace['arguments'] : [],
                'started_at_ms' => \is_int($trace['started_at_ms'] ?? null) || \is_float($trace['started_at_ms'] ?? null) ? (float) $trace['started_at_ms'] : null,
                'duration_ms' => \is_int($trace['duration_ms'] ?? null) || \is_float($trace['duration_ms'] ?? null) ? (float) $trace['duration_ms'] : null,
                'errored' => true === ($trace['errored'] ?? false),
                'mcp' => $stripped !== $name,
            ];
        }

        if (!\is_object($trace) || !method_exists($trace, 'getName')) {
            return null;
        }

        $name = $trace->getName();
        if (!\is_string($name) || '' === $name) {
            return null;
        }

        $stripped = $this->stripMcpPrefix($name);

        return [
            'name' => $stripped,
            'arguments' => method_exists($trace, 'getArguments') && \is_array($trace->getArguments()) ? $trace->getArguments() : [],
            'started_at_ms' => method_exists($trace, 'getStartedAtMs') && (\is_int($trace->getStartedAtMs()) || \is_float($trace->getStartedAtMs())) ? (float) $trace->getStartedAtMs() : null,
            'duration_ms' => method_exists($trace, 'getDurationMs') && (\is_int($trace->getDurationMs()) || \is_float($trace->getDurationMs())) ? (float) $trace->getDurationMs() : null,
            'errored' => method_exists($trace, 'isErrored') && true === $trace->isErrored(),
            'mcp' => $stripped !== $name,
        ];
    }

    /**
     * Strip Claude Code's `mcp__<server>__` namespacing so MCP tool names match
     * the bare names produced by other bridges (and by the scenarios'
     * `expected_tool_calls`). Names without the prefix pass through unchanged.
     */
    private function stripMcpPrefix(string $name): string
    {
        // Non-greedy match stops at the first `__` after the server segment, so
        // a tool name that itself contains `__` (rare) survives intact.
        return (string) preg_replace('/^mcp__[A-Za-z0-9._-]+?__/', '', $name);
    }
}
