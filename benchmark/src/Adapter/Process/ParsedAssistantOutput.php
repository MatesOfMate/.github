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
 * Structured slice of an assistant CLI's output.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class ParsedAssistantOutput
{
    /**
     * @param list<ToolCall> $toolCalls
     */
    public function __construct(
        public ?TokenUsage $tokenUsage = null,
        public array $toolCalls = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }
}
