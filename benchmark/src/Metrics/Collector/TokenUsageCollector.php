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
 * Surfaces input/output/total tokens reported by the adapter (or null when unavailable).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class TokenUsageCollector implements MetricsCollectorInterface
{
    public function collect(MetricsContext $context): array
    {
        $usage = $context->assistantResult?->tokenUsage;

        if (null === $usage) {
            return [
                'input_tokens' => null,
                'output_tokens' => null,
                'cached_tokens' => null,
                'total_tokens' => null,
            ];
        }

        return [
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'cached_tokens' => $usage->cachedTokens,
            'total_tokens' => $usage->totalTokens(),
        ];
    }
}
