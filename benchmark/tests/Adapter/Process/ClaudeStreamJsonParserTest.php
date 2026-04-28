<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Adapter\Process;

use MatesOfMate\Benchmark\Adapter\Process\ClaudeStreamJsonParser;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ClaudeStreamJsonParserTest extends TestCase
{
    public function testExtractsToolCallsAndUsageFromStreamJson(): void
    {
        $events = [
            ['type' => 'system'],
            ['type' => 'assistant', 'message' => ['content' => [
                ['type' => 'text', 'text' => 'thinking'],
                ['type' => 'tool_use', 'name' => 'Read', 'input' => ['path' => 'a.php']],
                ['type' => 'tool_use', 'name' => 'Bash', 'input' => ['command' => 'ls']],
            ]]],
            ['type' => 'result', 'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'cache_read_input_tokens' => 10,
            ]],
        ];

        $stdout = '';
        foreach ($events as $event) {
            $stdout .= json_encode($event)."\n";
        }

        $parsed = (new ClaudeStreamJsonParser())->parse($stdout, '');

        $this->assertCount(2, $parsed->toolCalls);
        $this->assertSame('Read', $parsed->toolCalls[0]->name);
        $this->assertSame('Bash', $parsed->toolCalls[1]->name);
        $this->assertNotNull($parsed->tokenUsage);
        $this->assertSame(100, $parsed->tokenUsage->inputTokens);
        $this->assertSame(50, $parsed->tokenUsage->outputTokens);
        $this->assertSame(10, $parsed->tokenUsage->cachedTokens);
    }

    public function testTolerantToInvalidLines(): void
    {
        $stdout = "garbage line\n".json_encode([
            'type' => 'result',
            'usage' => ['input_tokens' => 1, 'output_tokens' => 2],
        ])."\nmore garbage";

        $parsed = (new ClaudeStreamJsonParser())->parse($stdout, '');

        $this->assertSame(1, $parsed->tokenUsage?->inputTokens);
        $this->assertSame([], $parsed->toolCalls);
    }

    public function testEmptyStdoutReturnsEmptyResult(): void
    {
        $parsed = (new ClaudeStreamJsonParser())->parse('', '');

        $this->assertNull($parsed->tokenUsage);
        $this->assertSame([], $parsed->toolCalls);
    }
}
