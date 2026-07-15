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
use MatesOfMate\Benchmark\Runner\FixtureCopier;
use MatesOfMate\Benchmark\Runner\GitDiffCollector;
use MatesOfMate\Benchmark\Runner\WorkspaceFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class GitDiffCollectorTest extends TestCase
{
    private const string FIXTURE_PATH = __DIR__.'/../Fixtures/sample-fixture';

    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-git-'.bin2hex(random_bytes(4));

        $resolved = (new CommandExecutor())->execute('command -v git', sys_get_temp_dir());
        if (!$resolved->successful()) {
            $this->markTestSkipped('git is not available on PATH.');
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testInitializeCreatesGitRepository(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'sample', 1);

        (new GitDiffCollector(new CommandExecutor()))->initialize($workspace);

        $this->assertDirectoryExists($workspace->path.'/.git');
    }

    public function testCollectsDiffOfFilesChangedAfterBaseline(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'sample', 1);

        (new FixtureCopier())->copy(self::FIXTURE_PATH, $workspace);

        $collector = new GitDiffCollector(new CommandExecutor());
        $collector->initialize($workspace);
        $collector->seal($workspace);

        // Simulate AI-driven changes after the baseline is sealed.
        file_put_contents($workspace->path.'/src/index.php', '<?php echo "patched";'."\n");
        file_put_contents($workspace->path.'/NEW.md', "added\nline\n");

        $diff = $collector->collect($workspace);

        $this->assertContains('src/index.php', $diff->changedFiles);
        $this->assertContains('NEW.md', $diff->changedFiles);
        $this->assertGreaterThan(0, $diff->additions);
        $this->assertStringContainsString('patched', $diff->diff);
        $this->assertFalse($diff->isEmpty());
    }

    public function testEmptyDiffWhenNothingChanges(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'sample', 1);

        (new FixtureCopier())->copy(self::FIXTURE_PATH, $workspace);

        $collector = new GitDiffCollector(new CommandExecutor());
        $collector->initialize($workspace);
        $collector->seal($workspace);

        $diff = $collector->collect($workspace);

        $this->assertSame([], $diff->changedFiles);
        $this->assertSame(0, $diff->additions);
        $this->assertSame(0, $diff->deletions);
        $this->assertTrue($diff->isEmpty());
    }

    public function testCollectDiffsAgainstBaselineTagEvenAfterAssistantCommits(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'sample', 1);

        (new FixtureCopier())->copy(self::FIXTURE_PATH, $workspace);

        $executor = new CommandExecutor();
        $collector = new GitDiffCollector($executor);
        $collector->initialize($workspace);
        $collector->seal($workspace);

        // A stray `git commit` by the assistant must not erase the change set:
        // the diff is pinned to the baseline tag, not to a moving HEAD.
        file_put_contents($workspace->path.'/src/index.php', '<?php echo "patched";'."\n");
        $env = ['GIT_TERMINAL_PROMPT' => '0', 'GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null'];
        $executor->mustExecute('git add -A', $workspace->path, 'git add', env: $env);
        $executor->mustExecute('git commit -q -m "assistant commit"', $workspace->path, 'git commit', env: $env);

        $diff = $collector->collect($workspace);

        $this->assertContains('src/index.php', $diff->changedFiles);
        $this->assertStringContainsString('patched', $diff->diff);
    }

    public function testRestoreFilesRevertsBaselineFilesAndDeletesCreatedOnes(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'sample', 1);

        (new FixtureCopier())->copy(self::FIXTURE_PATH, $workspace);
        $original = (string) file_get_contents($workspace->path.'/src/index.php');

        $collector = new GitDiffCollector(new CommandExecutor());
        $collector->initialize($workspace);
        $collector->seal($workspace);

        file_put_contents($workspace->path.'/src/index.php', '<?php echo "tampered";'."\n");
        mkdir($workspace->path.'/tests');
        file_put_contents($workspace->path.'/tests/hack.php', '<?php exit(0);'."\n");

        $collector->restoreFiles($workspace, ['src/index.php', 'tests/hack.php']);

        $this->assertStringEqualsFile($workspace->path.'/src/index.php', $original);
        $this->assertFileDoesNotExist($workspace->path.'/tests/hack.php', 'Files that were not part of the baseline are removed.');
    }
}
