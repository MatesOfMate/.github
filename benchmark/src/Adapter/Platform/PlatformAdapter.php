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
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

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

        return AssistantRunResult::success(
            stdout: $stdout,
            durationMs: $durationMs,
            tokenUsage: $tokens,
            // Tool calls are intentionally empty: the platform's non-streaming
            // path keeps only the final `result` event, so per-tool details
            // are not surfaced. A streaming variant could populate this list.
            toolCalls: [],
        );
    }

    /**
     * Pick the model name passed to the platform for the given run.
     */
    protected function resolveModel(AssistantRunInput $input): string
    {
        return $input->model ?? $this->defaultModel;
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
        $content = $result->getContent();

        if (\is_string($content)) {
            return $content;
        }

        if (\is_iterable($content)) {
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
        if (null === $rawResult) {
            return null;
        }

        $data = $rawResult->getData();
        if (!\is_array($data)) {
            return null;
        }

        $usage = $data['usage'] ?? null;
        if (!\is_array($usage)) {
            return null;
        }

        $input = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
        $cached = (int) (
            ($usage['cache_read_input_tokens'] ?? 0)
            + ($usage['cache_creation_input_tokens'] ?? 0)
            + ($usage['cached_input_tokens'] ?? 0)
        );

        if (0 === $input && 0 === $output && 0 === $cached) {
            return null;
        }

        return new TokenUsage(
            inputTokens: $input,
            outputTokens: $output,
            cachedTokens: $cached,
        );
    }
}
