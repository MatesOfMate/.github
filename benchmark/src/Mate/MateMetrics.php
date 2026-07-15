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
 * Aggregated Mate tool-call metrics for a single scenario attempt.
 *
 * Mirrors the metric envelope described in `specs/05-mate-integration.md`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class MateMetrics
{
    /**
     * @param list<string> $toolNames
     * @param list<string> $expectedTools
     * @param list<string> $missingExpectedTools
     * @param list<string> $expectedToolsAny
     */
    public function __construct(
        public bool $enabled,
        public int $toolCallCount,
        public array $toolNames,
        public ?float $firstToolCallMs,
        public int $toolErrors,
        public array $expectedTools = [],
        public array $missingExpectedTools = [],
        public array $expectedToolsAny = [],
        public bool $anyToolMatched = false,
    ) {
    }

    public static function disabled(): self
    {
        return new self(
            enabled: false,
            toolCallCount: 0,
            toolNames: [],
            firstToolCallMs: null,
            toolErrors: 0,
        );
    }
}
