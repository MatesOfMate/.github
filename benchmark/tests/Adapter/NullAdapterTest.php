<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Adapter;

use MatesOfMate\Benchmark\Adapter\AssistantRunInput;
use MatesOfMate\Benchmark\Adapter\NullAdapter;
use MatesOfMate\Benchmark\Mate\MateConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class NullAdapterTest extends TestCase
{
    public function testReportsNameNull(): void
    {
        $this->assertSame('null', (new NullAdapter())->name());
    }

    public function testRunReturnsSuccessWithoutTokensOrToolCalls(): void
    {
        $adapter = new NullAdapter();
        $result = $adapter->run(new AssistantRunInput(
            workspacePath: '/tmp/does-not-matter',
            prompt: 'Find the bug.',
            model: 'mock-model',
            mateConfig: MateConfiguration::enabled(),
        ));

        $this->assertTrue($result->successful);
        $this->assertSame(0, $result->exitCode);
        $this->assertNull($result->tokenUsage);
        $this->assertSame([], $result->toolCalls);
        $this->assertStringContainsString('NullAdapter', $result->stdout);
        $this->assertStringContainsString('mock-model', $result->stdout);
        $this->assertStringContainsString('mate: enabled', $result->stdout);
    }

    public function testReflectsMateDisabledFlag(): void
    {
        $result = (new NullAdapter())->run(new AssistantRunInput(
            workspacePath: '/tmp',
            prompt: 'irrelevant',
            mateConfig: MateConfiguration::disabled(),
        ));

        $this->assertStringContainsString('mate: disabled', $result->stdout);
    }
}
