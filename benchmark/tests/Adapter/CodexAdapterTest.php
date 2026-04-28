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
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CodexAdapterTest extends TestCase
{
    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-codex-'.bin2hex(random_bytes(4));
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
        $this->assertSame('codex', (new CodexAdapter(binary: 'echo'))->name());
    }

    public function testRunCapturesUsageAndToolCallsFromFakeBinary(): void
    {
        $adapter = new CodexAdapter(binary: $this->fakeBinary());

        $result = $adapter->run(new AssistantRunInput(
            workspacePath: $this->tmp,
            prompt: 'do the thing',
            model: 'gpt-5',
        ));

        $this->assertTrue($result->successful);
        $this->assertSame(0, $result->exitCode);
        $this->assertCount(2, $result->toolCalls);
        $this->assertSame(800, $result->tokenUsage?->inputTokens);
        $this->assertSame(300, $result->tokenUsage?->outputTokens);
    }

    public function testTimeoutMarksResultAsTimedOut(): void
    {
        $sleeper = $this->tmp.'/sleeper.sh';
        file_put_contents($sleeper, "#!/usr/bin/env sh\nsleep 2\n");
        chmod($sleeper, 0o755);

        $adapter = new CodexAdapter(binary: $sleeper);

        $result = $adapter->run(new AssistantRunInput(
            workspacePath: $this->tmp,
            prompt: 'whatever',
            timeoutSeconds: 1,
        ));

        $this->assertFalse($result->successful);
        $this->assertTrue($result->timedOut);
    }

    private function fakeBinary(): string
    {
        $script = __DIR__.'/Fakes/fake-codex.php';

        return \PHP_BINARY.' '.escapeshellarg($script);
    }
}
