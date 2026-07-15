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
 * Reports counts of changed files and added/removed lines from the workspace diff.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class DiffMetricsCollector implements MetricsCollectorInterface
{
    public function collect(MetricsContext $context): array
    {
        $diff = $context->diff;

        if (!$diff instanceof \MatesOfMate\Benchmark\Runner\DiffResult) {
            return [
                'files_changed_count' => null,
                'diff_added_lines' => null,
                'diff_removed_lines' => null,
            ];
        }

        return [
            'files_changed_count' => \count($diff->changedFiles),
            'diff_added_lines' => $diff->additions,
            'diff_removed_lines' => $diff->deletions,
        ];
    }
}
