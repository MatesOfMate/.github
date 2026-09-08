<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Tests\Unit\Grouping;

use MatesOfMate\RectorExtension\Grouping\RuleGrouper;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class RuleGrouperTest extends TestCase
{
    private RuleGrouper $grouper;

    protected function setUp(): void
    {
        $this->grouper = new RuleGrouper();
    }

    public function testACleanRunHasNoGroups(): void
    {
        $this->assertSame([], $this->grouper->group([]));
    }

    public function testAGroupCountsTheFilesOneRuleChanged(): void
    {
        $groups = $this->grouper->group([
            \Rector\Php54\Rector\Array_\LongArrayToShortArrayRector::class => ['/a.php', '/b.php', '/c.php'],
        ]);

        $this->assertCount(1, $groups);
        $this->assertSame(3, $groups[0]['count']);
        $this->assertSame('LongArrayToShortArrayRector', $groups[0]['short']);
        $this->assertSame('Php54', $groups[0]['set']);
        $this->assertSame('g1', $groups[0]['id']);
    }

    public function testGroupsAreOrderedByHowManyFilesTheyTouched(): void
    {
        $groups = $this->grouper->group([
            'Rector\\A\\Rector\\X\\SmallRector' => ['/a.php'],
            'Rector\\B\\Rector\\Y\\WideRector' => ['/a.php', '/b.php', '/c.php'],
        ]);

        $this->assertSame([3, 1], array_column($groups, 'count'));
        $this->assertSame('WideRector', $groups[0]['short']);
    }

    /**
     * A file is normally rewritten by several rules at once, so groups overlap.
     * Each group counts the files it touched; it does not own them.
     */
    public function testOverlappingRulesEachCountTheSameFile(): void
    {
        $groups = $this->grouper->group([
            'Rector\\A\\Rector\\X\\OneRector' => ['/a.php', '/b.php'],
            'Rector\\B\\Rector\\Y\\TwoRector' => ['/a.php', '/b.php'],
        ]);

        $this->assertSame([2, 2], array_column($groups, 'count'));
    }

    public function testAFileListedTwiceForOneRuleIsCountedOnce(): void
    {
        $groups = $this->grouper->group([
            'Rector\\A\\Rector\\X\\OneRector' => ['/a.php', '/a.php', '/b.php'],
        ]);

        $this->assertSame(2, $groups[0]['count']);
        $this->assertSame(['/a.php', '/b.php'], $groups[0]['files']);
    }

    public function testAnUnnamespacedRuleStillGroups(): void
    {
        $groups = $this->grouper->group(['CustomRector' => ['/a.php']]);

        $this->assertSame('CustomRector', $groups[0]['short']);
        $this->assertSame('', $groups[0]['set']);
    }
}
