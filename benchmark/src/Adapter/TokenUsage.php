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
 * Token accounting reported by an assistant adapter.
 *
 * `inputTokens` are fresh (non-cached) prompt tokens; cache reads/writes are
 * tracked separately in `cachedTokens` because they cost a fraction of fresh
 * tokens and would otherwise dominate every total for CLI agents.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class TokenUsage
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public int $cachedTokens = 0,
        public ?float $costUsd = null,
    ) {
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens + $this->cachedTokens;
    }

    /**
     * Fresh token consumption (input + output, excluding cache traffic) —
     * the number efficiency should be judged on.
     */
    public function freshTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
