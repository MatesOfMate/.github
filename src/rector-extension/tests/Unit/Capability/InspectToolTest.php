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

use MatesOfMate\RectorExtension\Capability\InspectTool;
use MatesOfMate\RectorExtension\Discovery\ProjectContext;
use MatesOfMate\RectorExtension\Discovery\RectorDiscovery;
use MatesOfMate\RectorExtension\Formatter\ToonFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class InspectToolTest extends TestCase
{
    public function testExecuteFormatsInspectionWithoutRunningRector(): void
    {
        $context = ProjectContext::fromArray([
            'projectRoot' => '/project',
            'rectorInstalled' => false,
            'diagnostics' => ['missing rector'],
        ]);

        $discovery = $this->createMock(RectorDiscovery::class);
        $discovery->expects($this->once())
            ->method('inspect')
            ->willReturn($context);

        $formatter = $this->createMock(ToonFormatter::class);
        $formatter->expects($this->once())
            ->method('formatInspection')
            ->with($context)
            ->willReturn('inspection');

        $tool = new InspectTool($discovery, $formatter);

        $this->assertSame('inspection', $tool->execute());
    }
}
