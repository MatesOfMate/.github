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

use MatesOfMate\Benchmark\Adapter\Process\CodexJsonParser;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CodexJsonParserTest extends TestCase
{
    public function testExtractsToolCallsAndUsageFromJsonl(): void
    {
        $events = [
            ['msg' => ['type' => 'session_started']],
            ['msg' => ['type' => 'tool_call', 'name' => 'shell', 'arguments' => ['command' => 'ls']]],
            ['msg' => ['type' => 'function_call', 'name' => 'edit_file', 'arguments' => ['path' => 'a.php']]],
            ['msg' => ['type' => 'token_count', 'info' => ['total_token_usage' => ['input_tokens' => 800, 'output_tokens' => 300]]]],
        ];

        $stdout = '';
        foreach ($events as $event) {
            $stdout .= json_encode($event)."\n";
        }

        $parsed = (new CodexJsonParser())->parse($stdout, '');

        $this->assertCount(2, $parsed->toolCalls);
        $this->assertSame('shell', $parsed->toolCalls[0]->name);
        $this->assertSame('edit_file', $parsed->toolCalls[1]->name);
        $this->assertSame(800, $parsed->tokenUsage?->inputTokens);
        $this->assertSame(300, $parsed->tokenUsage?->outputTokens);
    }

    public function testRecognisesAlternativeUsageShape(): void
    {
        $stdout = json_encode([
            'msg' => [
                'type' => 'usage',
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 150, 'cache_read_input_tokens' => 25],
            ],
        ])."\n";

        $parsed = (new CodexJsonParser())->parse($stdout, '');

        $this->assertSame(200, $parsed->tokenUsage?->inputTokens);
        $this->assertSame(150, $parsed->tokenUsage?->outputTokens);
        $this->assertSame(25, $parsed->tokenUsage?->cachedTokens);
    }

    public function testEmptyOutputProducesEmptyResult(): void
    {
        $parsed = (new CodexJsonParser())->parse('', '');

        $this->assertNull($parsed->tokenUsage);
        $this->assertSame([], $parsed->toolCalls);
    }
}
