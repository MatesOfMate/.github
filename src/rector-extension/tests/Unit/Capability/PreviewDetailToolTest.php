<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Tests\Unit\Capability;

use MatesOfMate\RectorExtension\Cache\RunCache;
use MatesOfMate\RectorExtension\Capability\PreviewDetailTool;
use MatesOfMate\RectorExtension\Grouping\RuleGrouper;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class PreviewDetailToolTest extends TestCase
{
    private string $cacheDir;
    private RunCache $cache;
    private PreviewDetailTool $tool;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/rector-detail-'.bin2hex(random_bytes(4));
        $this->cache = new RunCache($this->cacheDir, 'rector-runs', 20);
        $this->tool = new PreviewDetailTool($this->cache);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDir.'/*/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->cacheDir.'/*') ?: [] as $dir) {
            @rmdir($dir);
        }
        @rmdir($this->cacheDir);
    }

    public function testAnUnknownRunIdIsReportedWithTheAvailableOnes(): void
    {
        $this->cache->store(['groups' => [], 'diffs' => []]);

        $decoded = $this->decode($this->tool->execute('nope'));

        $this->assertStringContainsString('Unknown run id', (string) $decoded['error']);
        $this->assertStringContainsString('Cached runs', (string) $decoded['hint']);
    }

    public function testAnEmptyCacheSaysToPreviewFirst(): void
    {
        $decoded = $this->decode($this->tool->execute('nope'));

        $this->assertStringContainsString('Run rector-preview first', (string) $decoded['hint']);
    }

    public function testEveryDiffComesBackWhenNothingIsNarrowed(): void
    {
        $decoded = $this->decode($this->tool->execute($this->storeRun()));

        $this->assertSame(3, $decoded['returned']);
        $this->assertArrayNotHasKey('truncated', $decoded);
    }

    public function testOnlyTheFilesOfOneRuleGroupComeBack(): void
    {
        $decoded = $this->decode($this->tool->execute($this->storeRun(), rule: 'g2'));

        $this->assertSame(1, $decoded['returned']);
        $this->assertStringContainsString('Order.php', (string) $decoded['diffs'][0]['file']);
    }

    public function testTheRuleCanBeGivenByItsClassName(): void
    {
        $decoded = $this->decode($this->tool->execute($this->storeRun(), rule: 'Rector\\B\\Rector\\Y\\NarrowRector'));

        $this->assertSame(1, $decoded['returned']);
    }

    public function testDiffsCanBeNarrowedToAFile(): void
    {
        $decoded = $this->decode($this->tool->execute($this->storeRun(), file: 'Invoice.php'));

        $this->assertSame(1, $decoded['returned']);
    }

    public function testAnUnknownRuleGroupListsTheRealOnes(): void
    {
        $decoded = $this->decode($this->tool->execute($this->storeRun(), rule: 'g99'));

        $this->assertStringContainsString('No rule group g99', (string) $decoded['error']);
        $this->assertNotEmpty($decoded['groups']);
    }

    /**
     * A short list that does not say it is short reads like a complete one.
     */
    public function testACutListSaysThatItWasCut(): void
    {
        $decoded = $this->decode($this->tool->execute($this->storeRun(), limit: 2));

        $this->assertSame(2, $decoded['returned']);
        $this->assertTrue($decoded['truncated']);
        $this->assertStringContainsString('raise limit', (string) $decoded['hint']);
    }

    private function storeRun(): string
    {
        $groups = (new RuleGrouper())->group([
            'Rector\\A\\Rector\\X\\WideRector' => ['/app/src/Invoice.php', '/app/src/Cart.php'],
            'Rector\\B\\Rector\\Y\\NarrowRector' => ['/app/src/Order.php'],
        ]);

        return $this->cache->store([
            'groups' => $groups,
            'diffs' => [
                ['file' => '/app/src/Invoice.php', 'diff' => '- old
+ new'],
                ['file' => '/app/src/Cart.php', 'diff' => '- old
+ new'],
                ['file' => '/app/src/Order.php', 'diff' => '- old
+ new'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $encoded): array
    {
        $decoded = ResponseEncoder::decode($encoded);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
