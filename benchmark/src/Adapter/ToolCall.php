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
 * Single tool invocation observed during an assistant run.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class ToolCall
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
        public ?string $result = null,
        public ?float $durationMs = null,
        public bool $errored = false,
        public ?float $startedAtMs = null,
        public bool $mcp = false,
    ) {
    }
}
