<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Common\Tests\Cache;

use MatesOfMate\Common\Cache\RunCache;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class RunCacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/common-run-cache-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $dir = $this->cacheDir.'/test-runs';
        @chmod($dir, 0o700);
        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
        @rmdir($this->cacheDir);
    }

    public function testAStoredRunComesBack(): void
    {
        $cache = $this->createCache();

        $id = $cache->store(['groups' => [['id' => 'g1']]]);

        $this->assertNotSame('', $id);

        $loaded = $cache->load($id);
        $this->assertNotNull($loaded);
        $this->assertSame([['id' => 'g1']], $loaded['groups']);
    }

    public function testAnUnknownIdIsNotAnError(): void
    {
        $this->assertNull($this->createCache()->load('does-not-exist'));
    }

    /**
     * The id arrives as a tool argument, so it must not be able to address
     * anything outside the cache directory.
     */
    public function testAnIdCannotEscapeTheCacheDirectory(): void
    {
        $cache = $this->createCache();
        $cache->store(['n' => 1]);

        $this->assertNull($cache->load('../../../etc/passwd'));
        $this->assertNull($cache->load('..'));
    }

    /**
     * Ids are the sort key for "newest first" and for eviction alike. At second
     * resolution, runs stored inside the same second sort by their random
     * suffix, so eviction removes an arbitrary run instead of the oldest.
     */
    public function testEvictionKeepsTheNewestRunsWhenManyLandInTheSameSecond(): void
    {
        $cache = $this->createCache(keep: 20);

        for ($i = 0; $i < 30; ++$i) {
            $cache->store(['n' => $i]);
        }

        $ids = $cache->ids();

        $this->assertCount(20, $ids);

        $newest = $cache->load($ids[0]);
        $oldestKept = $cache->load($ids[19]);
        $this->assertNotNull($newest);
        $this->assertNotNull($oldestKept);
        $this->assertSame(29, $newest['n']);
        $this->assertSame(10, $oldestKept['n']);
    }

    public function testNothingIsEvictedBelowTheLimit(): void
    {
        $cache = $this->createCache(keep: 20);

        for ($i = 0; $i < 5; ++$i) {
            $cache->store(['n' => $i]);
        }

        $this->assertCount(5, $cache->ids());
    }

    public function testNoPartialFileIsLeftBehind(): void
    {
        $cache = $this->createCache();
        $cache->store(['n' => 1]);

        $this->assertSame([], glob($this->cacheDir.'/test-runs/.*.tmp') ?: []);
    }

    public function testStoringFailsLoudlyWhenTheDirectoryCannotBeCreated(): void
    {
        $cache = new RunCache('/proc/nope', 'test-runs', 20);

        $this->expectException(\RuntimeException::class);

        $cache->store(['n' => 1]);
    }

    /**
     * The directory exists but cannot be written to: the mkdir guard never
     * fires here, so this is the failure the write itself has to catch. A
     * silent failure at this point would hand the agent a run id that is
     * already unloadable.
     */
    #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public function testStoringFailsLoudlyWhenTheDirectoryIsNotWritable(): void
    {
        $dir = $this->cacheDir.'/test-runs';
        mkdir($dir, 0o700, true);
        chmod($dir, 0o500);

        $cache = new RunCache($this->cacheDir, 'test-runs', 20);

        try {
            $cache->store(['n' => 1]);
            $this->markTestSkipped('the write succeeded despite the permission change, likely running as root.');
        } catch (\RuntimeException) {
        } finally {
            chmod($dir, 0o700);
        }
    }

    private function createCache(int $keep = 20): RunCache
    {
        return new RunCache($this->cacheDir, 'test-runs', $keep);
    }
}
