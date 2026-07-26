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
use MatesOfMate\RectorExtension\Discovery\ProjectContext;
use MatesOfMate\RectorExtension\Discovery\RectorDiscovery;
use MatesOfMate\RectorExtension\Formatter\ToonFormatter;
use MatesOfMate\RectorExtension\Parser\RectorOutputParser;
use MatesOfMate\RectorExtension\Runner\RectorRunner;
use MatesOfMate\RectorExtension\Validation\PathValidator;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class PreviewToolTest extends TestCase
{
    public function testExecuteReturnsStructuredFailureForInvalidPath(): void
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
            ->with('../README.md')
            ->willThrowException(new \InvalidArgumentException('Path must be inside the project root: ../README.md'));

        $runner = $this->createMock(RectorRunner::class);
        $runner->expects($this->never())->method('preview');

        $tool = new PreviewTool(
            $discovery,
            $validator,
            $runner,
            $this->createMock(RectorOutputParser::class),
            new ToonFormatter(),
        );

        $payload = json_decode($tool->execute(path: '../README.md'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame('preview', $payload['workflow']);
        $this->assertSame('FAILED', $payload['status']);
        $this->assertSame(1, $payload['exit_code']);
        $this->assertSame(['Path must be inside the project root: ../README.md'], $payload['diagnostics']);
        $this->assertSame(['path' => '../README.md'], $payload['rejected_input']);
    }

    public function testExecuteRefusesMissingConfigurationBeforeRunningRector(): void
    {
        $context = ProjectContext::fromArray([
            'projectRoot' => '/project',
            'rectorInstalled' => true,
            'preferredStrategy' => [
                'type' => 'local-binary',
                'command' => [\PHP_BINARY, '/project/vendor/bin/rector'],
            ],
        ]);

        $discovery = $this->createMock(RectorDiscovery::class);
        $discovery->method('inspect')->willReturn($context);

        $runner = $this->createMock(RectorRunner::class);
        $runner->expects($this->never())->method('preview');

        $tool = new PreviewTool(
            $discovery,
            $this->createMock(PathValidator::class),
            $runner,
            $this->createMock(RectorOutputParser::class),
            $this->createMock(ToonFormatter::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rector configuration was not found.');

        $tool->execute();
    }
}
