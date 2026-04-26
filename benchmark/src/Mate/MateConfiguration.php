<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Mate;

/**
 * Per-workspace Mate configuration handed to the adapter.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class MateConfiguration
{
    /**
     * @param array<string, string> $env
     * @param list<string>          $expectedTools
     */
    public function __construct(
        public bool $enabled,
        public ?string $configPath = null,
        public array $env = [],
        public array $expectedTools = [],
    ) {
    }

    public static function disabled(): self
    {
        return new self(enabled: false);
    }

    /**
     * @param list<string>          $expectedTools
     * @param array<string, string> $env
     */
    public static function enabled(?string $configPath = null, array $expectedTools = [], array $env = []): self
    {
        return new self(
            enabled: true,
            configPath: $configPath,
            env: $env,
            expectedTools: $expectedTools,
        );
    }
}
