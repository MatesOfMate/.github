<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Metrics\Collector;

use MatesOfMate\Benchmark\Metrics\MetricsCollectorInterface;
use MatesOfMate\Benchmark\Metrics\MetricsContext;

/**
 * Reports the wall-clock duration of the attempt.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class DurationCollector implements MetricsCollectorInterface
{
    public function collect(MetricsContext $context): array
    {
        return [
            'duration_ms' => $context->totalDurationMs,
            // First-code-change timing requires runtime instrumentation in the
            // adapter (which we do not have yet); leave it null per spec.
            'time_to_first_code_change_ms' => null,
        ];
    }
}
