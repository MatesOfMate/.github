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

    public function testExtractsToolCallsFromPlatformMetadata(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($this->stubDeferred(
                text: 'patched',
                toolCallTraces: [[
                    'name' => 'symfony_logs',
                    'arguments' => ['channel' => 'app'],
                    'started_at_ms' => 1200.0,
                    'duration_ms' => 8.0,
                    'errored' => false,
                ]],
            ));

        $result = (new ClaudeCodeAdapter($platform))->run(new AssistantRunInput(
            workspacePath: '/tmp/workspace',
            prompt: 'find the bug',
        ));

        $this->assertCount(1, $result->toolCalls);
        $this->assertSame('symfony_logs', $result->toolCalls[0]->name);
        $this->assertSame(['channel' => 'app'], $result->toolCalls[0]->arguments);
        $this->assertSame(1200.0, $result->toolCalls[0]->startedAtMs);
        $this->assertSame(8.0, $result->toolCalls[0]->durationMs);
        $this->assertFalse($result->toolCalls[0]->errored);
    }

    public function testStripsClaudeCodeMcpPrefixFromMcpToolNames(): void
    {
        // Claude Code emits MCP tool calls as `mcp__<server>__<tool>`. Codex
        // emits the bare `<tool>`. Without stripping, scenarios'
        // `expected_tool_calls` (which use the bare names) never match Claude
        // tool calls and `mate_tool_usage` scores 0 even when the tool was
        // exercised correctly.
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($this->stubDeferred(
                text: 'patched',
                toolCallTraces: [
                    ['name' => 'mcp__symfony-ai-mate__monolog-search', 'arguments' => ['term' => 'service']],
                    ['name' => 'mcp__symfony_ai_mate__monolog-search', 'arguments' => ['term' => 'runtime']],
                    ['name' => 'Read', 'arguments' => ['path' => 'src/services.php']],
                ],
            ));

        $result = (new ClaudeCodeAdapter($platform))->run(new AssistantRunInput(
            workspacePath: '/tmp/workspace',
            prompt: 'find the bug',
        ));

        $this->assertCount(3, $result->toolCalls);
        $this->assertSame('monolog-search', $result->toolCalls[0]->name);
        $this->assertSame('monolog-search', $result->toolCalls[1]->name);
        $this->assertSame('Read', $result->toolCalls[2]->name);
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

    public function testForwardsBypassPermissionsForUnattendedRuns(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(static fn (array $options): bool => 'bypassPermissions' === ($options['permission_mode'] ?? null)),
            )
            ->willReturn($this->stubDeferred(text: 'ok'));

        (new ClaudeCodeAdapter($platform))->run(new AssistantRunInput(
            workspacePath: '/tmp',
            prompt: 'fix',
        ));
    }

    public function testDisablesSessionPersistenceToIsolateAttempts(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(static fn (array $options): bool => true === ($options['no_session_persistence'] ?? null)),
            )
            ->willReturn($this->stubDeferred(text: 'ok'));

        (new ClaudeCodeAdapter($platform))->run(new AssistantRunInput(
            workspacePath: '/tmp',
            prompt: 'fix',
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
     * @param array<string, int>|null                   $usage
     * @param list<array<string, mixed>>|array<int, object> $toolCallTraces
     */
    private function stubDeferred(string $text, ?array $usage = null, array $toolCallTraces = []): DeferredResult
    {
        $rawData = ['result' => $text];
        if (null !== $usage) {
            $rawData['usage'] = $usage;
        }

        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn($rawData);

        $textResult = new TextResult($text);
        $textResult->setRawResult($rawResult);
        if ([] !== $toolCallTraces) {
            $textResult->getMetadata()->add('tool_call_traces', $toolCallTraces);
        }

        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('convert')->willReturn($textResult);

        return new DeferredResult($converter, $rawResult);
    }
}
