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
use MatesOfMate\Benchmark\Adapter\Process\CodexJsonParser;
use MatesOfMate\Benchmark\Adapter\Process\ProcessAdapter;

/**
 * Drives the `codex` CLI in non-interactive mode and parses its JSONL stream.
 *
 * Defaults to `codex exec --json --skip-git-repo-check` and pipes the prompt
 * via stdin (`-` is implied). When Mate is enabled the config path is exported
 * to the child process as `MATE_BENCHMARK_CONFIG` so MCP servers wired through
 * Mate can pick it up.
 *
 * The binary path can be overridden with `BENCHMARK_CODEX_BIN`. Additional
 * flags can be appended via `BENCHMARK_CODEX_ARGS`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CodexAdapter extends ProcessAdapter
{
    public const NAME = 'codex';
    public const ENV_BINARY = 'BENCHMARK_CODEX_BIN';
    public const ENV_ARGS = 'BENCHMARK_CODEX_ARGS';

    public function __construct(
        ?string $binary = null,
        ?AssistantOutputParserInterface $parser = null,
        private readonly string $extraArgs = '',
    ) {
        parent::__construct(
            binary: $binary ?? (getenv(self::ENV_BINARY) ?: 'codex'),
            parser: $parser ?? new CodexJsonParser(),
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
            'exec',
            '--json',
            '--skip-git-repo-check',
            '--sandbox=workspace-write',
        ];

        if (null !== $input->model) {
            $parts[] = '-m '.escapeshellarg($input->model);
        }

        if ('' !== $this->extraArgs) {
            $parts[] = $this->extraArgs;
        }

        return implode(' ', $parts);
    }
}
