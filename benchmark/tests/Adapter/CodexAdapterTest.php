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
use MatesOfMate\Benchmark\Adapter\CodexAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CodexAdapterTest extends TestCase
{
    public function testReportsName(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $this->assertSame('codex', (new CodexAdapter($platform))->name());
    }

    public function testInvokesPlatformWithDefaultModelAndWorkspaceWriteSandbox(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with(
                'gpt-5-codex',
                'do the thing',
                $this->callback(static fn (array $options): bool => 'workspace-write' === ($options['sandbox'] ?? null)
                    && '/tmp/workspace' === ($options['cwd'] ?? null)),
            )
            ->willReturn($this->stubDeferred('done', ['input_tokens' => 800, 'output_tokens' => 300]));

        $result = (new CodexAdapter($platform))->run(new AssistantRunInput(
            workspacePath: '/tmp/workspace',
            prompt: 'do the thing',
        ));

        $this->assertTrue($result->successful);
        $this->assertSame('done', $result->stdout);
        $this->assertSame(800, $result->tokenUsage?->inputTokens);
    }

    public function testFailureFromPlatformIsCapturedNotThrown(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('codex exited 2'));

        $result = (new CodexAdapter($platform))->run(new AssistantRunInput(
            workspacePath: '/tmp',
            prompt: 'whatever',
        ));

        $this->assertFalse($result->successful);
        $this->assertStringContainsString('codex exited 2', (string) $result->errorMessage);
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
