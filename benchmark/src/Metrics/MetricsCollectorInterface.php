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
 * Produces a slice of metrics from a {@see MetricsContext}.
 *
 * Implementations must always return every key they know about, using `null`
 * for unsupported values rather than omitting the key entirely.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface MetricsCollectorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function collect(MetricsContext $context): array;
}
