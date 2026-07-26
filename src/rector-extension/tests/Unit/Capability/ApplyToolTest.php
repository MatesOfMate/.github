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
use MatesOfMate\RectorExtension\Discovery\ProjectContext;
use MatesOfMate\RectorExtension\Discovery\RectorDiscovery;
use MatesOfMate\RectorExtension\Formatter\ToonFormatter;
use MatesOfMate\RectorExtension\Parser\ParsedRectorResult;
use MatesOfMate\RectorExtension\Parser\RectorOutputParser;
use MatesOfMate\RectorExtension\Runner\RectorRunner;
use MatesOfMate\RectorExtension\Validation\PathValidator;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ApplyToolTest extends TestCase
{
    public function testExecutePassesApplyModeToRunnerWithoutAdditionalConfirmation(): void
    {
        $context = ProjectContext::fromArray([
            'projectRoot' => '/project',
            'rectorInstalled' => true,
            'configuration' => '/project/rector.php',
            'preferredStrategy' => [
                'type' => 'local-binary',
                'command' => [\PHP_BINARY, '/project/vendor/bin/rector'],
            ],
        ]);

        $discovery = $this->createMock(RectorDiscovery::class);
        $discovery->method('inspect')->willReturn($context);

        $validator = $this->createMock(PathValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with('src')
            ->willReturn('src');

        $runner = $this->createMock(RectorRunner::class);
        $runner->expects($this->once())
            ->method('apply')
            ->with($context->preferredStrategy, '/project/rector.php', 'src', false, false);

        $parser = $this->createMock(RectorOutputParser::class);
        $parser->method('parse')->willReturn(ParsedRectorResult::empty(false));

        $formatter = $this->createMock(ToonFormatter::class);
        $formatter->method('format')->willReturn('formatted');

        $tool = new ApplyTool($discovery, $validator, $runner, $parser, $formatter);

        $this->assertSame('formatted', $tool->execute(path: 'src'));
    }
}
