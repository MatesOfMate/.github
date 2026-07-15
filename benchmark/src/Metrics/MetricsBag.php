<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Metrics;

/**
 * Stable, JSON-serialisable collection of benchmark metrics for a single attempt.
 *
 * Every required and optional metric key from `specs/06-metrics-collection.md`
 * is always present; unsupported values are `null`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class MetricsBag
{
    public const REQUIRED_KEYS = [
        'duration_ms',
        'input_tokens',
        'output_tokens',
        'cached_tokens',
        'fresh_tokens',
        'total_tokens',
        'cost_usd',
        'tool_call_count',
        'mate_tool_call_count',
        'mate_tool_names',
        'files_changed_count',
        'diff_added_lines',
        'diff_removed_lines',
        'commands_passed',
        'commands_failed',
    ];

    public const OPTIONAL_KEYS = [
        'time_to_first_tool_call_ms',
        'time_to_first_code_change_ms',
        'first_mate_tool_call_ms',
        'redundant_tool_call_count',
        'tool_error_count',
    ];

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(public array $values)
    {
    }

    /**
     * Builds a bag with every documented key set to `null`.
     */
    public static function empty(): self
    {
        $values = [];
        foreach ([...self::REQUIRED_KEYS, ...self::OPTIONAL_KEYS] as $key) {
            $values[$key] = null;
        }

        return new self($values);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function with(array $extra): self
    {
        return new self(array_replace($this->values, $extra));
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
