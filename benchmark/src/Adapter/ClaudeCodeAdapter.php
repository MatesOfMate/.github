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

use MatesOfMate\Benchmark\Adapter\Process\AssistantOutputParserInterface;
use MatesOfMate\Benchmark\Adapter\Process\ClaudeStreamJsonParser;
use MatesOfMate\Benchmark\Adapter\Process\ProcessAdapter;

/**
 * Drives the `claude` CLI in non-interactive mode and parses its JSONL stream.
 *
 * Defaults to `claude --print --output-format=stream-json --verbose --bare` and
 * pipes the prompt via stdin. The Mate config path produced by
 * {@see \MatesOfMate\Benchmark\Mate\MateConfigurationFactory} is forwarded via
 * `--mcp-config` whenever Mate is enabled for the run.
 *
 * The binary path can be overridden with `BENCHMARK_CLAUDE_BIN`. Additional
 * flags can be appended via `BENCHMARK_CLAUDE_ARGS`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ClaudeCodeAdapter extends ProcessAdapter
{
    public const NAME = 'claude';
    public const ENV_BINARY = 'BENCHMARK_CLAUDE_BIN';
    public const ENV_ARGS = 'BENCHMARK_CLAUDE_ARGS';

    public function __construct(
        ?string $binary = null,
        ?AssistantOutputParserInterface $parser = null,
        private readonly string $extraArgs = '',
    ) {
        parent::__construct(
            binary: $binary ?? (getenv(self::ENV_BINARY) ?: 'claude'),
            parser: $parser ?? new ClaudeStreamJsonParser(),
        );
    }

    public static function withDefaults(): self
    {
        return new self(
            extraArgs: getenv(self::ENV_ARGS) ?: '',
        );
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function buildCommand(\MatesOfMate\Benchmark\Adapter\AssistantRunInput $input): string
    {
        $parts = [
            escapeshellcmd($this->binary),
            '--print',
            '--output-format=stream-json',
            '--verbose',
            '--bare',
            '--dangerously-skip-permissions',
        ];

        if (null !== $input->model) {
            $parts[] = '--model='.escapeshellarg($input->model);
        }

        $mateConfig = $input->mateConfig;
        if ($mateConfig->enabled && null !== $mateConfig->configPath) {
            $parts[] = '--mcp-config='.escapeshellarg($mateConfig->configPath);
        }

        if ('' !== $this->extraArgs) {
            $parts[] = $this->extraArgs;
        }

        return implode(' ', $parts);
    }
}
