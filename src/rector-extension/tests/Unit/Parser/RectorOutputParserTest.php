<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Tests\Unit\Parser;

use MatesOfMate\RectorExtension\Parser\RectorOutputParser;
use MatesOfMate\RectorExtension\Runner\RunResult;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class RectorOutputParserTest extends TestCase
{
    public function testParseJsonOutputExtractsChangedFileCount(): void
    {
        $output = json_encode([
            'totals' => [
                'changed_files' => 2,
                'errors' => 0,
            ],
            'file_diffs' => [
                [
                    'file' => 'src/Foo.php',
                    'diff' => "--- Original\n+++ New",
                    'applied_rectors' => ['Rector\\FooRector'],
                ],
                [
                    'file' => 'src/Bar.php',
                    'diff' => "--- Original\n+++ New",
                    'applied_rectors' => ['Rector\\BarRector'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);

        $result = (new RectorOutputParser())->parse(new RunResult(
            command: ['vendor/bin/rector', 'process', '--dry-run'],
            strategy: 'local-binary',
            workingDirectory: '/project',
            exitCode: 1,
            output: $output,
            errorOutput: '',
            timedOut: false,
        ), true);

        $this->assertSame(2, $result->changedFileCount);
        $this->assertSame(['src/Foo.php', 'src/Bar.php'], $result->changedFiles);
        $this->assertSame(['Rector\\FooRector', 'Rector\\BarRector'], $result->rules);
        $this->assertTrue($result->preview);
    }

    public function testParseFallsBackToRawOutputWhenJsonIsUnavailable(): void
    {
        $result = (new RectorOutputParser())->parse(new RunResult(
            command: ['vendor/bin/rector', 'process', '--dry-run'],
            strategy: 'local-binary',
            workingDirectory: '/project',
            exitCode: 1,
            output: '2 files with changes',
            errorOutput: 'error',
            timedOut: false,
        ), true);

        $this->assertSame('2 files with changes', $result->rawOutput);
        $this->assertSame('error', $result->errorOutput);
        $this->assertSame(['Could not parse Rector JSON output; raw output is included.'], $result->diagnostics);
    }
}
