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
use Symfony\AI\Platform\Bridge\Codex\Factory as CodexFactory;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Drives the Codex CLI via the `symfony/ai-codex-platform` bridge.
 *
 * The bridge owns subprocess management and JSONL parsing; this adapter only
 * marshals benchmark inputs into `Platform::invoke()` arguments and forces a
 * write-capable sandbox so the assistant can edit workspace files. Override
 * the binary with `BENCHMARK_CODEX_BIN`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CodexAdapter extends PlatformAdapter
{
    public const NAME = 'codex';
    public const DEFAULT_MODEL = 'gpt-5-codex';
    public const ENV_BINARY = 'BENCHMARK_CODEX_BIN';

    public static function withDefaults(): self
    {
        $binary = getenv(self::ENV_BINARY);

        return new self(CodexFactory::createPlatform(
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

    /**
     * @return array<string, mixed>
     */
    protected function buildOptions(\MatesOfMate\Benchmark\Adapter\AssistantRunInput $input): array
    {
        $options = parent::buildOptions($input);
        // Codex needs a writable sandbox to apply patches inside the workspace.
        $options['sandbox'] = 'workspace-write';

        return $options;
    }
}
