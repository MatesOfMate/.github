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

use MatesOfMate\Benchmark\Runner\WorkspaceFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class WorkspaceFactoryTest extends TestCase
{
    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-workspace-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testCreatesWorkspaceAtExpectedPath(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'bug.example', 1);

        $expected = $this->tmp.'/runs/run-1/bug.example/1/workspace';
        $this->assertSame($expected, $workspace->path);
        $this->assertDirectoryExists($expected);
        $this->assertSame(1, $workspace->attempt);
        $this->assertFalse($workspace->keep);
    }

    public function testEachAttemptGetsDistinctWorkspace(): void
    {
        $factory = new WorkspaceFactory($this->tmp);

        $first = $factory->create('run-1', 'bug.example', 1);
        $second = $factory->create('run-1', 'bug.example', 2);

        $this->assertNotSame($first->path, $second->path);
        $this->assertDirectoryExists($first->path);
        $this->assertDirectoryExists($second->path);
    }

    public function testCreateClearsExistingDirectory(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $first = $factory->create('run-1', 'bug.example', 1);

        file_put_contents($first->path.'/leftover.txt', 'old');
        $this->assertFileExists($first->path.'/leftover.txt');

        $factory->create('run-1', 'bug.example', 1);
        $this->assertFileDoesNotExist($first->path.'/leftover.txt');
    }

    public function testDestroyRemovesUnkeptWorkspace(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'bug.example', 1, keep: false);

        $factory->destroy($workspace);

        $this->assertDirectoryDoesNotExist($workspace->path);
    }

    public function testDestroyPreservesKeptWorkspace(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'bug.example', 1, keep: true);

        $factory->destroy($workspace);

        $this->assertDirectoryExists($workspace->path);
    }

    public function testRejectsZeroOrNegativeAttempt(): void
    {
        $factory = new WorkspaceFactory($this->tmp);

        $this->expectException(\InvalidArgumentException::class);
        $factory->create('run-1', 'bug.example', 0);
    }

    public function testGenerateRunIdProducesSortableValue(): void
    {
        $factory = new WorkspaceFactory($this->tmp);

        $first = $factory->generateRunId(new \DateTimeImmutable('2026-04-26 22:43:17'));
        $second = $factory->generateRunId(new \DateTimeImmutable('2026-04-27 09:00:00'));

        $this->assertMatchesRegularExpression('/^\d{8}-\d{6}-[a-f0-9]{6}$/', $first);
        $this->assertLessThan(0, strcmp($first, $second));
    }
}
