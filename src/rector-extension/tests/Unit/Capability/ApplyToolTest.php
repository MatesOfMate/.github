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

use MatesOfMate\RectorExtension\Capability\ApplyTool;
use MatesOfMate\RectorExtension\Workflow\RectorWorkflow;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ApplyToolTest extends TestCase
{
    public function testExecuteRequestsTheWriteWorkflowWithoutAdditionalConfirmation(): void
    {
        $workflow = $this->createMock(RectorWorkflow::class);
        $workflow->expects($this->once())
            ->method('run')
            ->with(false, 'src', null, false, false, 'default')
            ->willReturn('formatted');

        $this->assertSame('formatted', (new ApplyTool($workflow))->execute(path: 'src'));
    }

    public function testExecuteForwardsAllOptions(): void
    {
        $workflow = $this->createMock(RectorWorkflow::class);
        $workflow->expects($this->once())
            ->method('run')
            ->with(false, 'src/Foo.php', 'rector-ci.php', true, true, 'summary')
            ->willReturn('formatted');

        $tool = new ApplyTool($workflow);

        $this->assertSame('formatted', $tool->execute(
            path: 'src/Foo.php',
            configuration: 'rector-ci.php',
            debug: true,
            rulesSummary: true,
            mode: 'summary',
        ));
    }
}
