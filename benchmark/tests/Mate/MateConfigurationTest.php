<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Mate;

use MatesOfMate\Benchmark\Mate\MateConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MateConfigurationTest extends TestCase
{
    public function testDisabledFactoryProducesInactiveConfig(): void
    {
        $config = MateConfiguration::disabled();

        $this->assertFalse($config->enabled);
        $this->assertNull($config->configPath);
        $this->assertSame([], $config->env);
        $this->assertSame([], $config->expectedTools);
    }

    public function testEnabledFactoryRetainsExpectedToolsAndEnv(): void
    {
        $config = MateConfiguration::enabled(
            configPath: '/tmp/mate.json',
            expectedTools: ['symfony_logs'],
            env: ['MATE' => '1'],
        );

        $this->assertTrue($config->enabled);
        $this->assertSame('/tmp/mate.json', $config->configPath);
        $this->assertSame(['symfony_logs'], $config->expectedTools);
        $this->assertSame(['MATE' => '1'], $config->env);
    }
}
