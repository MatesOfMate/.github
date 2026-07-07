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
class CodexAdapterTest extends TestCase
{
    private ?string $previousHome = null;

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
                'gpt-5.3-codex',
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

    public function testExtractsToolCallsFromPlatformMetadata(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($this->stubDeferred(
                'done',
                toolCallTraces: [[
                    'name' => 'symfony_container',
                    'arguments' => ['service' => 'logger'],
                    'started_at_ms' => 2200.0,
                    'duration_ms' => 11.0,
                    'errored' => true,
                ]],
            ));

        $home = $this->writableCodexHome();

        try {
            $result = (new CodexAdapter($platform))->run(new AssistantRunInput(
                workspacePath: '/tmp/workspace',
                prompt: 'do the thing',
            ));

            $this->assertCount(1, $result->toolCalls);
            $this->assertSame('symfony_container', $result->toolCalls[0]->name);
            $this->assertSame(['service' => 'logger'], $result->toolCalls[0]->arguments);
            $this->assertSame(2200.0, $result->toolCalls[0]->startedAtMs);
            $this->assertSame(11.0, $result->toolCalls[0]->durationMs);
            $this->assertTrue($result->toolCalls[0]->errored);
        } finally {
            (new \Symfony\Component\Filesystem\Filesystem())->remove($home);
            $this->restoreHome();
        }
    }

    public function testMateEnabledTranslatesMcpConfigIntoCodexOverridesAndBypassesApprovals(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(static function (array $options): bool {
                    $config = $options['config'] ?? null;

                    return \is_array($config)
                        && true === ($options['dangerously_bypass_approvals_and_sandbox'] ?? null)
                        && !isset($options['ask_for_approval'])
                        && !isset($options['sandbox'])
                        && \in_array('mcp_servers.symfony_ai_mate.command="./vendor/bin/mate"', $config, true)
                        && \in_array('mcp_servers.symfony_ai_mate.args=["serve","--force-keep-alive"]', $config, true)
                        && !isset($options['mcp_config']);
                }),
            )
            ->willReturn($this->stubDeferred('ok'));

        $tmp = sys_get_temp_dir().'/bench-codex-mcp-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($tmp, <<<'JSON'
{
    "mcpServers": {
        "symfony-ai-mate": {
            "command": "./vendor/bin/mate",
            "args": ["serve", "--force-keep-alive"]
        }
    }
}
JSON);

        $home = $this->writableCodexHome();

        try {
            $result = (new CodexAdapter($platform))->run(new AssistantRunInput(
                workspacePath: '/tmp/workspace',
                prompt: 'whatever',
                mateConfig: MateConfiguration::enabled(configPath: $tmp),
            ));

            $this->assertTrue($result->successful);
        } finally {
            @unlink($tmp);
            (new \Symfony\Component\Filesystem\Filesystem())->remove($home);
            $this->restoreHome();
        }
    }

    public function testPrefersWorkspaceCodexWrapperForMateEnabledRuns(): void
    {
        $workspace = sys_get_temp_dir().'/bench-codex-workspace-'.bin2hex(random_bytes(4));
        mkdir($workspace.'/bin', 0777, true);
        file_put_contents($workspace.'/bin/codex', "#!/bin/sh\necho workspace-wrapper-selected >&2\nexit 1\n");
        chmod($workspace.'/bin/codex', 0755);

        $tmp = sys_get_temp_dir().'/bench-codex-mcp-wrapper-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($tmp, <<<'JSON'
{
    "mcpServers": {
        "symfony-ai-mate": {
            "command": "./vendor/bin/mate",
            "args": ["serve", "--force-keep-alive"]
        }
    }
}
JSON);

        $home = $this->writableCodexHome();

        putenv(CodexAdapter::ENV_BINARY.'=/usr/bin/false');

        try {
            $adapter = CodexAdapter::withDefaults();

            $result = $adapter->run(new AssistantRunInput(
                workspacePath: $workspace,
                prompt: 'whatever',
                mateConfig: MateConfiguration::enabled(configPath: $tmp),
            ));

            $this->assertFalse($result->successful);
            $this->assertStringContainsString('workspace-wrapper-selected', (string) $result->errorMessage);
        } finally {
            putenv(CodexAdapter::ENV_BINARY);
            @unlink($tmp);
            (new \Symfony\Component\Filesystem\Filesystem())->remove([$home, $workspace]);
            $this->restoreHome();
        }
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

    public function testFailsFastWhenSessionStorageIsNotAccessible(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $home = sys_get_temp_dir().'/bench-codex-home-'.bin2hex(random_bytes(4));
        mkdir($home.'/.codex/sessions', 0777, true);
        chmod($home.'/.codex/sessions', 0000);

        try {
            $previous = getenv('HOME');
            putenv('HOME='.$home);

            $result = (new CodexAdapter($platform))->run(new AssistantRunInput(
                workspacePath: '/tmp',
                prompt: 'whatever',
            ));

            $this->assertFalse($result->successful);
            $this->assertStringContainsString('session storage is not accessible', (string) $result->errorMessage);
        } finally {
            chmod($home.'/.codex/sessions', 0700);
            (new \Symfony\Component\Filesystem\Filesystem())->remove($home);

            if (false === $previous) {
                putenv('HOME');
            } else {
                putenv('HOME='.$previous);
            }
        }
    }

    public function testMateConfigWithoutServersFailsClearly(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $tmp = sys_get_temp_dir().'/bench-codex-mcp-empty-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($tmp, '{"mcpServers":{}}');

        $home = $this->writableCodexHome();

        try {
            $result = (new CodexAdapter($platform))->run(new AssistantRunInput(
                workspacePath: '/tmp/workspace',
                prompt: 'whatever',
                mateConfig: MateConfiguration::enabled(configPath: $tmp),
            ));

            $this->assertFalse($result->successful);
            $this->assertStringContainsString('does not contain any MCP servers', (string) $result->errorMessage);
        } finally {
            @unlink($tmp);
            (new \Symfony\Component\Filesystem\Filesystem())->remove($home);
            $this->restoreHome();
        }
    }

    /**
     * @param array<string, int>|null                        $usage
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

    private function writableCodexHome(): string
    {
        $this->previousHome = getenv('HOME') ?: null;

        $home = sys_get_temp_dir().'/bench-codex-home-'.bin2hex(random_bytes(4));
        mkdir($home.'/.codex/sessions', 0777, true);
        putenv('HOME='.$home);

        return $home;
    }

    private function restoreHome(): void
    {
        if (null === $this->previousHome) {
            putenv('HOME');
        } else {
            putenv('HOME='.$this->previousHome);
        }

        $this->previousHome = null;
    }
}
