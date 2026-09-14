<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\PhpStanExtension\Tests\Unit\Capability;

use MatesOfMate\PhpStanExtension\Capability\GenerateBaselineTool;
use MatesOfMate\PhpStanExtension\Config\ConfigurationDetector;
use MatesOfMate\PhpStanExtension\Runner\PhpStanRunner;
use MatesOfMate\PhpStanExtension\Runner\RunResult;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class GenerateBaselineToolTest extends TestCase
{
    public function testExecuteGeneratesBaselineWithDefaultFile(): void
    {
        $runResult = new RunResult(0, '', '');

        $runner = $this->createMock(PhpStanRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->with('analyse', ['--generate-baseline=phpstan-baseline.neon'])
            ->willReturn($runResult);

        $configDetector = $this->createMock(ConfigurationDetector::class);
        $configDetector->method('detect')->willReturn(null);

        $tool = new GenerateBaselineTool($runner, $configDetector);
        $output = $tool->execute();

        $result = json_decode($output, true);
        $this->assertTrue($result['success']);
        $this->assertSame('phpstan-baseline.neon', $result['baseline_file']);
        $this->assertFalse($result['baseline_imported']);
        $this->assertArrayHasKey('hint', $result);
        $this->assertStringContainsString('phpstan-baseline.neon', $result['hint']);
    }

    public function testExecuteGeneratesBaselineWithCustomFile(): void
    {
        $runResult = new RunResult(0, '', '');

        $runner = $this->createMock(PhpStanRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->with('analyse', ['--generate-baseline=custom-baseline.neon'])
            ->willReturn($runResult);

        $configDetector = $this->createMock(ConfigurationDetector::class);
        $configDetector->method('detect')->willReturn(null);

        $tool = new GenerateBaselineTool($runner, $configDetector);
        $output = $tool->execute(baselineFile: 'custom-baseline.neon');

        $result = json_decode($output, true);
        $this->assertTrue($result['success']);
        $this->assertSame('custom-baseline.neon', $result['baseline_file']);
    }

    public function testExecutePassesConfigurationAndLevelAndPath(): void
    {
        $runResult = new RunResult(0, '', '');

        $runner = $this->createMock(PhpStanRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->with('analyse', [
                '--configuration', 'phpstan.neon',
                '--level', '8',
                'src/',
                '--generate-baseline=phpstan-baseline.neon',
            ])
            ->willReturn($runResult);

        $configDetector = $this->createMock(ConfigurationDetector::class);
        $configDetector->method('detect')->willReturn(null);

        $tool = new GenerateBaselineTool($runner, $configDetector);
        $output = $tool->execute(
            configuration: 'phpstan.neon',
            level: 8,
            path: 'src/',
        );

        $result = json_decode($output, true);
        $this->assertTrue($result['success']);
    }

    public function testExecuteDetectsBaselineImportedInConfig(): void
    {
        $tempConfigFile = tempnam(sys_get_temp_dir(), 'phpstan_test_').'.neon';
        file_put_contents($tempConfigFile, "includes:\n    - phpstan-baseline.neon\n");

        try {
            $runResult = new RunResult(0, '', '');

            $runner = $this->createMock(PhpStanRunner::class);
            $runner->method('run')->willReturn($runResult);

            $configDetector = $this->createMock(ConfigurationDetector::class);
            $configDetector->method('detect')->willReturn($tempConfigFile);

            $tool = new GenerateBaselineTool($runner, $configDetector);
            $output = $tool->execute();

            $result = json_decode($output, true);
            $this->assertTrue($result['success']);
            $this->assertTrue($result['baseline_imported']);
            $this->assertArrayNotHasKey('hint', $result);
        } finally {
            @unlink($tempConfigFile);
        }
    }

    public function testExecuteReturnsErrorOnFailure(): void
    {
        $runResult = new RunResult(1, 'some output', 'error message');

        $runner = $this->createMock(PhpStanRunner::class);
        $runner->method('run')->willReturn($runResult);

        $configDetector = $this->createMock(ConfigurationDetector::class);
        $configDetector->method('detect')->willReturn(null);

        $tool = new GenerateBaselineTool($runner, $configDetector);
        $output = $tool->execute();

        $result = json_decode($output, true);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('error message', $result['error']);
    }

    public function testExecuteBaselineNotImportedWhenNoConfigFile(): void
    {
        $runResult = new RunResult(0, '', '');

        $runner = $this->createMock(PhpStanRunner::class);
        $runner->method('run')->willReturn($runResult);

        $configDetector = $this->createMock(ConfigurationDetector::class);
        $configDetector->method('detect')->willReturn(null);

        $tool = new GenerateBaselineTool($runner, $configDetector);
        $output = $tool->execute();

        $result = json_decode($output, true);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['baseline_imported']);
    }
}
