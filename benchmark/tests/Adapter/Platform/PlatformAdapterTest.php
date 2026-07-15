<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Adapter\Platform;

use MatesOfMate\Benchmark\Adapter\AssistantRunInput;
use MatesOfMate\Benchmark\Adapter\Platform\PlatformAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall as PlatformToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class PlatformAdapterTest extends TestCase
{
    public function testExtractsTextFromMultiPartResult(): void
    {
        $result = $this->runWithMultiPart([
            new ToolCallResult([new PlatformToolCall('call_1', 'Bash', ['command' => 'ls'])]),
            new TextResult('First part. '),
            new TextResult('Second part.'),
        ]);

        $this->assertTrue($result->successful);
        $this->assertSame('First part. Second part.', $result->stdout);
    }

    public function testExtractsToolCallsFromMultiPartResultAndFlagsMcpCalls(): void
    {
        $result = $this->runWithMultiPart([
            new ToolCallResult([new PlatformToolCall('call_1', 'mcp__symfony-ai-mate__monolog-search', ['term' => 'error'])]),
            new ToolCallResult([new PlatformToolCall('call_2', 'Bash', ['command' => 'vendor/bin/phpunit'])]),
            new TextResult('done'),
        ]);

        $this->assertCount(2, $result->toolCalls);

        // The MCP namespace prefix is stripped so scenario expectations match
        // across bridges, and the call is flagged as MCP for Mate accounting.
        $this->assertSame('monolog-search', $result->toolCalls[0]->name);
        $this->assertTrue($result->toolCalls[0]->mcp);
        $this->assertSame(['term' => 'error'], $result->toolCalls[0]->arguments);

        $this->assertSame('Bash', $result->toolCalls[1]->name);
        $this->assertFalse($result->toolCalls[1]->mcp);
    }

    public function testNormalizesOpenAiStyleCachedInputTokensAndReadsCost(): void
    {
        $result = $this->runWithMultiPart(
            [new TextResult('ok')],
            rawData: [
                'usage' => [
                    'input_tokens' => 1_000,
                    'output_tokens' => 200,
                    // OpenAI-style: cached tokens are a subset of input_tokens.
                    'cached_input_tokens' => 600,
                ],
                'total_cost_usd' => 0.12,
            ],
        );

        $this->assertNotNull($result->tokenUsage);
        $this->assertSame(400, $result->tokenUsage->inputTokens);
        $this->assertSame(200, $result->tokenUsage->outputTokens);
        $this->assertSame(600, $result->tokenUsage->cachedTokens);
        $this->assertSame(600, $result->tokenUsage->freshTokens());
        $this->assertSame(0.12, $result->tokenUsage->costUsd);
    }

    public function testClaudeStyleCacheFieldsAreNotSubtractedFromInput(): void
    {
        $result = $this->runWithMultiPart(
            [new TextResult('ok')],
            rawData: [
                'usage' => [
                    // Claude-style: input_tokens already excludes cache traffic.
                    'input_tokens' => 100,
                    'output_tokens' => 50,
                    'cache_read_input_tokens' => 900,
                    'cache_creation_input_tokens' => 100,
                ],
            ],
        );

        $this->assertNotNull($result->tokenUsage);
        $this->assertSame(100, $result->tokenUsage->inputTokens);
        $this->assertSame(50, $result->tokenUsage->outputTokens);
        $this->assertSame(1_000, $result->tokenUsage->cachedTokens);
        $this->assertSame(150, $result->tokenUsage->freshTokens());
        $this->assertNull($result->tokenUsage->costUsd);
    }

    /**
     * @param non-empty-list<ResultInterface> $parts
     * @param array<string, mixed>            $rawData
     */
    private function runWithMultiPart(array $parts, array $rawData = []): \MatesOfMate\Benchmark\Adapter\AssistantRunResult
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn($rawData);

        $multiPart = new MultiPartResult($parts);
        $multiPart->setRawResult($rawResult);

        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('convert')->willReturn($multiPart);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('invoke')->willReturn(new DeferredResult($converter, $rawResult));

        $adapter = new class($platform, 'test-model') extends PlatformAdapter {
            public function name(): string
            {
                return 'platform-stub';
            }
        };

        return $adapter->run(new AssistantRunInput(
            workspacePath: '/tmp/workspace',
            prompt: 'do the thing',
        ));
    }
}
