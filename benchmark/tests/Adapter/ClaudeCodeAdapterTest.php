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
use MatesOfMate\Benchmark\Adapter\ClaudeCodeAdapter;
use MatesOfMate\Benchmark\Mate\MateConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ClaudeCodeAdapterTest extends TestCase
{
    public function testReportsName(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $this->assertSame('claude', (new ClaudeCodeAdapter($platform))->name());
    }

    public function testInvokesPlatformWithDefaultModelAndExtractsTextAndTokens(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with(
                'sonnet',
                'find the bug',
                $this->callback(static function (array $options): bool {
                    return '/tmp/workspace' === ($options['cwd'] ?? null);
                }),
            )
            ->willReturn($this->stubDeferred(
                text: 'patched',
                usage: ['input_tokens' => 1234, 'output_tokens' => 567, 'cache_read_input_tokens' => 100],
            ));

        $adapter = new ClaudeCodeAdapter($platform);
        $result = $adapter->run(new AssistantRunInput(
            workspacePath: '/tmp/workspace',
            prompt: 'find the bug',
        ));

        $this->assertTrue($result->successful);
        $this->assertSame('patched', $result->stdout);
        $this->assertNotNull($result->tokenUsage);
        $this->assertSame(1234, $result->tokenUsage->inputTokens);
        $this->assertSame(567, $result->tokenUsage->outputTokens);
        $this->assertSame(100, $result->tokenUsage->cachedTokens);
        $this->assertSame([], $result->toolCalls);
    }

    public function testForwardsMateConfigAsMcpConfigOption(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(static fn (array $options): bool => '/tmp/.mate/config.json' === ($options['mcp_config'] ?? null)),
            )
            ->willReturn($this->stubDeferred(text: 'ok'));

        (new ClaudeCodeAdapter($platform))->run(new AssistantRunInput(
            workspacePath: '/tmp/workspace',
            prompt: 'use mate',
            mateConfig: MateConfiguration::enabled(configPath: '/tmp/.mate/config.json'),
        ));
    }

    public function testInputModelOverridesDefault(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with('opus', $this->anything(), $this->anything())
            ->willReturn($this->stubDeferred(text: 'ok'));

        (new ClaudeCodeAdapter($platform))->run(new AssistantRunInput(
            workspacePath: '/tmp',
            prompt: 'test',
            model: 'opus',
        ));
    }

    public function testPlatformExceptionBecomesFailure(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('CLI not found'));

        $result = (new ClaudeCodeAdapter($platform))->run(new AssistantRunInput(
            workspacePath: '/tmp',
            prompt: 'fix',
        ));

        $this->assertFalse($result->successful);
        $this->assertSame('CLI not found', $result->errorMessage);
        $this->assertNull($result->tokenUsage);
    }

    public function testMissingUsageDataLeavesTokenUsageNull(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('invoke')->willReturn($this->stubDeferred(text: 'ok'));

        $result = (new ClaudeCodeAdapter($platform))->run(new AssistantRunInput(
            workspacePath: '/tmp',
            prompt: 'irrelevant',
        ));

        $this->assertTrue($result->successful);
        $this->assertNull($result->tokenUsage);
    }

    /**
     * @param array<string, int>|null $usage
     */
    private function stubDeferred(string $text, ?array $usage = null): DeferredResult
    {
        $rawData = ['result' => $text];
        if (null !== $usage) {
            $rawData['usage'] = $usage;
        }

        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn($rawData);

        $textResult = new TextResult($text);
        $textResult->setRawResult($rawResult);

        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('convert')->willReturn($textResult);

        return new DeferredResult($converter, $rawResult);
    }
}
