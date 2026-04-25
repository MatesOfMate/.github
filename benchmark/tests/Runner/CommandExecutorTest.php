<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Runner;

use MatesOfMate\Benchmark\Runner\CommandExecutor;
use MatesOfMate\Benchmark\Runner\Exception\CommandFailedException;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CommandExecutorTest extends TestCase
{
    public function testCapturesStdoutExitAndDuration(): void
    {
        $executor = new CommandExecutor();
        $result = $executor->execute('php -r "echo 42;"', sys_get_temp_dir());

        $this->assertSame(0, $result->exitCode);
        $this->assertSame('42', $result->stdout);
        $this->assertGreaterThan(0.0, $result->durationMs);
        $this->assertTrue($result->successful());
        $this->assertFalse($result->timedOut);
    }

    public function testCapturesStderr(): void
    {
        $executor = new CommandExecutor();
        $result = $executor->execute('php -r "fwrite(STDERR, \"boom\");"', sys_get_temp_dir());

        $this->assertSame(0, $result->exitCode);
        $this->assertSame('boom', $result->stderr);
    }

    public function testCapturesNonZeroExit(): void
    {
        $executor = new CommandExecutor();
        $result = $executor->execute('php -r "exit(7);"', sys_get_temp_dir());

        $this->assertSame(7, $result->exitCode);
        $this->assertFalse($result->successful());
    }

    public function testTimeoutMarksResultAsTimedOut(): void
    {
        $executor = new CommandExecutor();
        $result = $executor->execute('php -r "sleep(2);"', sys_get_temp_dir(), timeoutSeconds: 1);

        $this->assertTrue($result->timedOut);
        $this->assertFalse($result->successful());
    }

    public function testMustExecuteReturnsResultOnSuccess(): void
    {
        $executor = new CommandExecutor();
        $result = $executor->mustExecute('php -r "echo \"ok\";"', sys_get_temp_dir());

        $this->assertSame('ok', $result->stdout);
    }

    public function testMustExecuteThrowsOnFailure(): void
    {
        $executor = new CommandExecutor();

        $this->expectException(CommandFailedException::class);
        $executor->mustExecute('php -r "exit(2);"', sys_get_temp_dir(), 'setup');
    }
}
