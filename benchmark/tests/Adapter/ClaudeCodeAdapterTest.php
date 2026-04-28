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
use Symfony\Component\Filesystem\Filesystem;

/**
 * Drives the adapter against a fake Claude binary so the suite stays offline.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ClaudeCodeAdapterTest extends TestCase
{
    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-claude-'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testReportsName(): void
    {
        $this->assertSame('claude', (new ClaudeCodeAdapter(binary: 'echo'))->name());
    }

    public function testRunCapturesUsageAndToolCallsFromFakeBinary(): void
    {
        $adapter = new ClaudeCodeAdapter(binary: $this->fakeBinary());

        $result = $adapter->run(new AssistantRunInput(
            workspacePath: $this->tmp,
            prompt: 'find the bug',
            model: 'sonnet',
        ));

        $this->assertTrue($result->successful);
        $this->assertSame(0, $result->exitCode);
        $this->assertCount(2, $result->toolCalls);
        $this->assertSame(1234, $result->tokenUsage?->inputTokens);
        $this->assertSame(567, $result->tokenUsage?->outputTokens);
    }

    public function testFailedBinaryProducesFailureResult(): void
    {
        $adapter = new ClaudeCodeAdapter(binary: '/usr/bin/false');

        $result = $adapter->run(new AssistantRunInput(
            workspacePath: $this->tmp,
            prompt: 'irrelevant',
        ));

        $this->assertFalse($result->successful);
        $this->assertNotSame(0, $result->exitCode);
        $this->assertNotNull($result->errorMessage);
    }

    public function testMateConfigPathFlowsToBinary(): void
    {
        $adapter = new ClaudeCodeAdapter(binary: $this->fakeBinary());
        $configPath = $this->tmp.'/mate.json';
        file_put_contents($configPath, '{}');

        $result = $adapter->run(new AssistantRunInput(
            workspacePath: $this->tmp,
            prompt: 'with mate',
            mateConfig: MateConfiguration::enabled(configPath: $configPath, expectedTools: ['symfony_logs']),
        ));

        $this->assertTrue($result->successful);
        // The fake binary echoes the --mcp-config argument back into the result event;
        // JSON encodes slashes, so we look for the file basename instead of the absolute path.
        $this->assertStringContainsString('mate.json', $result->stdout);
    }

    private function fakeBinary(): string
    {
        $script = __DIR__.'/Fakes/fake-claude.php';
        // Wrap the script invocation so escapeshellcmd preserves the argument.
        return \PHP_BINARY.' '.escapeshellarg($script);
    }
}
