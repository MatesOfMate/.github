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

use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Adapter\TokenUsage;
use MatesOfMate\Benchmark\Adapter\ToolCall;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class AssistantRunResultTest extends TestCase
{
    public function testSuccessFactoryProducesPositiveResult(): void
    {
        $tokens = new TokenUsage(100, 200);
        $tools = [new ToolCall('symfony_logs')];

        $result = AssistantRunResult::success(
            stdout: 'done',
            durationMs: 12.5,
            tokenUsage: $tokens,
            toolCalls: $tools,
        );

        $this->assertTrue($result->successful);
        $this->assertSame(0, $result->exitCode);
        $this->assertSame(12.5, $result->durationMs);
        $this->assertSame(300, $result->tokenUsage?->totalTokens());
        $this->assertSame($tools, $result->toolCalls);
        $this->assertNull($result->errorMessage);
    }

    public function testFailureFactoryProducesNegativeResult(): void
    {
        $result = AssistantRunResult::failure(
            errorMessage: 'boom',
            exitCode: 7,
            stderr: 'panic',
            timedOut: true,
        );

        $this->assertFalse($result->successful);
        $this->assertSame(7, $result->exitCode);
        $this->assertSame('boom', $result->errorMessage);
        $this->assertNull($result->tokenUsage);
        $this->assertTrue($result->timedOut);
    }

    public function testTokenUsageMayBeNullForUnsupportedAdapters(): void
    {
        $result = AssistantRunResult::success(stdout: '', durationMs: 0.0);

        $this->assertNull($result->tokenUsage);
    }
}
