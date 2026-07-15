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
use MatesOfMate\Benchmark\Runner\FixtureCopier;
use MatesOfMate\Benchmark\Runner\GitDiffCollector;
use MatesOfMate\Benchmark\Runner\WorkspaceFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * End-to-end check covering all milestone-03 acceptance criteria.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class IsolationIntegrationTest extends TestCase
{
    private const string FIXTURE_PATH = __DIR__.'/../Fixtures/sample-fixture';

    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-iso-'.bin2hex(random_bytes(4));

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

    public function testTwoRepeatsProduceSeparateWorkspaces(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $first = $factory->create('run-1', 'sample', 1);
        $second = $factory->create('run-1', 'sample', 2);

        (new FixtureCopier())->copy(self::FIXTURE_PATH, $first);
        (new FixtureCopier())->copy(self::FIXTURE_PATH, $second);

        // Mutating one must not bleed into the other.
        file_put_contents($first->path.'/marker.txt', 'first');

        $this->assertFileExists($first->path.'/marker.txt');
        $this->assertFileDoesNotExist($second->path.'/marker.txt');
    }

    public function testFullSetupBaselineDiffFlow(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'sample', 1);

        $copier = new FixtureCopier();
        $executor = new CommandExecutor();
        $collector = new GitDiffCollector($executor);

        $copier->copy(self::FIXTURE_PATH, $workspace);

        // Setup command (cross-platform): write a generated file that the baseline must include.
        $setup = $executor->mustExecute('php -r "file_put_contents(\"generated.txt\", \"setup\");"', $workspace->path, 'setup');
        $this->assertTrue($setup->successful());

        $collector->initialize($workspace);
        $collector->seal($workspace);

        // Simulate AI changes after sealing the baseline.
        file_put_contents($workspace->path.'/src/index.php', '<?php echo "ai-fix";'."\n");
        file_put_contents($workspace->path.'/PATCH.md', "explanation\n");

        $diff = $collector->collect($workspace);

        // The setup-generated file is part of the baseline so it must NOT appear in the AI diff.
        $this->assertNotContains('generated.txt', $diff->changedFiles);
        $this->assertContains('src/index.php', $diff->changedFiles);
        $this->assertContains('PATCH.md', $diff->changedFiles);
        $this->assertGreaterThan(0, $diff->additions);
    }

    public function testFailingSetupStopsTheScenarioWithClearError(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'sample', 1);

        (new FixtureCopier())->copy(self::FIXTURE_PATH, $workspace);

        $executor = new CommandExecutor();

        try {
            $executor->mustExecute('php -r "fwrite(STDERR, \"oh no\"); exit(3);"', $workspace->path, 'setup');
            $this->fail('Expected CommandFailedException to be thrown.');
        } catch (CommandFailedException $exception) {
            $this->assertSame(3, $exception->result->exitCode);
            $this->assertStringContainsStringIgnoringCase('setup', $exception->getMessage());
            $this->assertStringContainsString('oh no', $exception->getMessage());
        }
    }

    public function testKeepWorkspacePreservesDirectory(): void
    {
        $factory = new WorkspaceFactory($this->tmp);

        $kept = $factory->create('run-1', 'sample', 1, keep: true);
        $disposable = $factory->create('run-1', 'sample', 2, keep: false);

        $factory->destroy($kept);
        $factory->destroy($disposable);

        $this->assertDirectoryExists($kept->path);
        $this->assertDirectoryDoesNotExist($disposable->path);
    }
}
