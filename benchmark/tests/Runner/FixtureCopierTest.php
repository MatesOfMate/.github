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

use MatesOfMate\Benchmark\Runner\Exception\FixtureNotFoundException;
use MatesOfMate\Benchmark\Runner\FixtureCopier;
use MatesOfMate\Benchmark\Runner\WorkspaceFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class FixtureCopierTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__.'/../Fixtures/sample-fixture';

    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-copier-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testCopiesFixtureTreeIntoWorkspace(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'sample', 1);

        (new FixtureCopier())->copy(self::FIXTURE_PATH, $workspace);

        $this->assertFileExists($workspace->path.'/manifest.yaml');
        $this->assertFileExists($workspace->path.'/src/index.php');
        $this->assertSame(
            file_get_contents(self::FIXTURE_PATH.'/src/index.php'),
            file_get_contents($workspace->path.'/src/index.php'),
        );
    }

    public function testOriginalFixtureIsNotMutated(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'sample', 1);

        $copier = new FixtureCopier();
        $copier->copy(self::FIXTURE_PATH, $workspace);

        // Mutate the workspace copy.
        file_put_contents($workspace->path.'/src/index.php', '<?php echo "tampered";');
        file_put_contents($workspace->path.'/new-file.txt', 'extra');

        // Original must remain unchanged.
        $this->assertSame('<?php echo "hello";'."\n", file_get_contents(self::FIXTURE_PATH.'/src/index.php'));
        $this->assertFileDoesNotExist(self::FIXTURE_PATH.'/new-file.txt');
    }

    public function testThrowsWhenFixtureMissing(): void
    {
        $factory = new WorkspaceFactory($this->tmp);
        $workspace = $factory->create('run-1', 'sample', 1);

        $this->expectException(FixtureNotFoundException::class);
        (new FixtureCopier())->copy(__DIR__.'/../Fixtures/does-not-exist', $workspace);
    }
}
