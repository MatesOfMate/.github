<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Scoring;

use MatesOfMate\Benchmark\Scenario\Scenario;

/**
 * Weight configuration for the {@see ScoreCalculator}.
 *
 * Defaults follow the formula from `PLAN.md`. Scenario YAML may override the
 * weights via `evaluation.weights`; values are normalised so percentage- and
 * fraction-style configurations both work.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class ScoreWeights
{
    /**
     * @param array<string, float> $weights
     */
    public function __construct(public array $weights)
    {
    }

    public static function defaults(): self
    {
        return new self([
            'functional' => 0.40,
            'root_cause' => 0.20,
            'mate_tool_usage' => 0.15,
            'minimality' => 0.10,
            'verification' => 0.10,
            'efficiency' => 0.05,
        ]);
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw, ?self $fallback = null): self
    {
        $clean = [];
        foreach ($raw as $key => $value) {
            if (\is_string($key) && is_numeric($value) && (float) $value >= 0.0) {
                $clean[$key] = (float) $value;
            }
        }

        if ([] === $clean) {
            return $fallback ?? self::defaults();
        }

        return (new self($clean))->normalized();
    }

    public static function fromScenario(Scenario $scenario, ?self $fallback = null): self
    {
        $raw = $scenario->evaluation['weights'] ?? null;

        if (!\is_array($raw) || [] === $raw) {
            return $fallback ?? self::defaults();
        }

        return self::fromArray($raw, $fallback);
    }

    public function normalized(): self
    {
        $sum = array_sum($this->weights);
        if ($sum <= 0) {
            return $this;
        }

        $normalised = [];
        foreach ($this->weights as $key => $value) {
            $normalised[$key] = $value / $sum;
        }

        return new self($normalised);
    }
}
