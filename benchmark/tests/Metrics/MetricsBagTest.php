<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Metrics;

use MatesOfMate\Benchmark\Metrics\MetricsBag;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MetricsBagTest extends TestCase
{
    public function testEmptyBagContainsAllRequiredAndOptionalKeysAsNull(): void
    {
        $bag = MetricsBag::empty();

        foreach (MetricsBag::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $bag->toArray());
            $this->assertNull($bag->get($key), \sprintf('Required key "%s" must default to null.', $key));
        }
        foreach (MetricsBag::OPTIONAL_KEYS as $key) {
            $this->assertArrayHasKey($key, $bag->toArray());
            $this->assertNull($bag->get($key));
        }
    }

    public function testWithMergesValuesPreservingOtherKeys(): void
    {
        $bag = MetricsBag::empty()->with(['duration_ms' => 12.5]);

        $this->assertSame(12.5, $bag->get('duration_ms'));
        $this->assertNull($bag->get('input_tokens'));
        $this->assertCount(\count(MetricsBag::REQUIRED_KEYS) + \count(MetricsBag::OPTIONAL_KEYS), $bag->toArray());
    }

    public function testGetReturnsNullForUnknownKey(): void
    {
        $bag = MetricsBag::empty();

        $this->assertNull($bag->get('does.not.exist'));
    }
}
