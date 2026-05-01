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

use MatesOfMate\Benchmark\Adapter\Platform\PlatformAdapter;
use Symfony\AI\Platform\Bridge\ClaudeCode\Factory as ClaudeCodeFactory;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Drives the Claude Code CLI via the `symfony/ai-claude-code-platform` bridge.
 *
 * The bridge owns subprocess management, stream-json parsing and
 * `--mcp-config` plumbing; this adapter only marshals benchmark inputs into
 * `Platform::invoke()` arguments. Override the binary with
 * `BENCHMARK_CLAUDE_BIN`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ClaudeCodeAdapter extends PlatformAdapter
{
    public const NAME = 'claude';
    public const DEFAULT_MODEL = 'sonnet';
    public const ENV_BINARY = 'BENCHMARK_CLAUDE_BIN';

    public static function withDefaults(): self
    {
        $binary = getenv(self::ENV_BINARY);

        return new self(ClaudeCodeFactory::createPlatform(
            cliBinary: false === $binary || '' === $binary ? null : $binary,
            timeout: 600,
        ));
    }

    public function __construct(PlatformInterface $platform, string $defaultModel = self::DEFAULT_MODEL)
    {
        parent::__construct($platform, $defaultModel);
    }

    public function name(): string
    {
        return self::NAME;
    }
}
