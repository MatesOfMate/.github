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
use MatesOfMate\Benchmark\Runner\CommandResult;

/**
 * Counts how many setup, baseline and verification commands passed or failed.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CommandResultCollector implements MetricsCollectorInterface
{
    public function collect(MetricsContext $context): array
    {
        $passed = 0;
        $failed = 0;

        foreach ([...$context->setupResults, ...$context->baselineResults, ...$context->verificationResults] as $result) {
            \assert($result instanceof CommandResult);
            if ($result->successful()) {
                ++$passed;
            } else {
                ++$failed;
            }
        }

        return [
            'commands_passed' => $passed,
            'commands_failed' => $failed,
        ];
    }
}
