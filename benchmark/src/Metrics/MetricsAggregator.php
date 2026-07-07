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

use MatesOfMate\Benchmark\Metrics\Collector\CommandResultCollector;
use MatesOfMate\Benchmark\Metrics\Collector\DiffMetricsCollector;
use MatesOfMate\Benchmark\Metrics\Collector\DurationCollector;
use MatesOfMate\Benchmark\Metrics\Collector\TokenUsageCollector;
use MatesOfMate\Benchmark\Metrics\Collector\ToolUsageCollector;

/**
 * Runs every registered collector and merges their output into a single {@see MetricsBag}.
 *
 * Defaults wire the five spec-mandated collectors (duration, tokens, tool usage,
 * diff, command results); additional collectors can be passed in for custom
 * benchmark suites or extensions.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MetricsAggregator
{
    /**
     * @var list<MetricsCollectorInterface>
     */
    private array $collectors;

    /**
     * @param iterable<MetricsCollectorInterface>|null $collectors
     */
    public function __construct(?iterable $collectors = null)
    {
        if (null === $collectors) {
            $this->collectors = self::defaultCollectors();

            return;
        }

        $list = [];
        foreach ($collectors as $collector) {
            $list[] = $collector;
        }
        $this->collectors = $list;
    }

    public function aggregate(MetricsContext $context): MetricsBag
    {
        $bag = MetricsBag::empty();

        foreach ($this->collectors as $collector) {
            $bag = $bag->with($collector->collect($context));
        }

        return $bag;
    }

    /**
     * @return list<MetricsCollectorInterface>
     */
    public static function defaultCollectors(): array
    {
        return [
            new DurationCollector(),
            new TokenUsageCollector(),
            new ToolUsageCollector(),
            new DiffMetricsCollector(),
            new CommandResultCollector(),
        ];
    }
}
