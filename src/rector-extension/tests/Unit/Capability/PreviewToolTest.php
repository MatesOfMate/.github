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

use MatesOfMate\RectorExtension\Capability\PreviewTool;
use MatesOfMate\RectorExtension\Workflow\RectorWorkflow;
use PHPUnit\Framework\TestCase;

/**
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class PreviewToolTest extends TestCase
{
    public function testExecuteAlwaysRequestsThePreviewWorkflow(): void
    {
        $workflow = $this->createMock(RectorWorkflow::class);
        $workflow->expects($this->once())
            ->method('run')
            ->with(true, 'src', null, false, false, 'default')
            ->willReturn('formatted');

        $this->assertSame('formatted', (new PreviewTool($workflow))->execute(path: 'src'));
    }

    public function testExecuteForwardsAllOptions(): void
    {
        $workflow = $this->createMock(RectorWorkflow::class);
        $workflow->expects($this->once())
            ->method('run')
            ->with(true, 'src/Foo.php', 'rector-ci.php', true, true, 'detailed')
            ->willReturn('formatted');

        $tool = new PreviewTool($workflow);

        $this->assertSame('formatted', $tool->execute(
            path: 'src/Foo.php',
            configuration: 'rector-ci.php',
            debug: true,
            rulesSummary: true,
            mode: 'detailed',
        ));
    }
}
