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
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
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
        $this->assertSame([
            'Rector\\FooRector' => ['src/Foo.php'],
            'Rector\\BarRector' => ['src/Bar.php'],
        ], $result->ruleFiles);
        $this->assertTrue($result->preview);
    }

    /**
     * `ruleFiles` is what turns "one diff per file" into "this rule touched N
     * files": a single rule firing across several files must associate with
     * all of them, and a file changed by several rules must appear under each.
     */
    public function testRuleFilesAssociatesEachRuleWithEveryFileItChanged(): void
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
                    'applied_rectors' => ['Rector\\WideRector', 'Rector\\NarrowRector'],
                ],
                [
                    'file' => 'src/Bar.php',
                    'diff' => "--- Original\n+++ New",
                    'applied_rectors' => ['Rector\\WideRector'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $result = (new RectorOutputParser())->parse(new RunResult(
            command: ['vendor/bin/rector', 'process', '--dry-run'],
            strategy: 'local-binary',
            workingDirectory: '/project',
            exitCode: 1,
            output: $output,
            errorOutput: '',
            timedOut: false,
        ), true);

        $this->assertSame([
            'Rector\\WideRector' => ['src/Foo.php', 'src/Bar.php'],
            'Rector\\NarrowRector' => ['src/Foo.php'],
        ], $result->ruleFiles);
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

    public function testParseExtractsRectorErrors(): void
    {
        $output = json_encode([
            'totals' => [
                'changed_files' => 0,
                'errors' => 1,
            ],
            'file_diffs' => [],
            'errors' => [
                [
                    'message' => "Syntax error, unexpected '{', expecting T_VARIABLE",
                    'file' => 'src/Broken.php',
                    'line' => 2,
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $result = (new RectorOutputParser())->parse(new RunResult(
            command: ['vendor/bin/rector', 'process', '--dry-run'],
            strategy: 'local-binary',
            workingDirectory: '/project',
            exitCode: 0,
            output: $output,
            errorOutput: '',
            timedOut: false,
        ), true);

        $this->assertSame(1, $result->errorCount);
        $this->assertSame([[
            'message' => "Syntax error, unexpected '{', expecting T_VARIABLE",
            'file' => 'src/Broken.php',
            'line' => 2,
        ]], $result->errors);
    }

    public function testParseReportsNoErrorsForACleanRun(): void
    {
        $result = (new RectorOutputParser())->parse(new RunResult(
            command: ['vendor/bin/rector', 'process'],
            strategy: 'local-binary',
            workingDirectory: '/project',
            exitCode: 0,
            output: '{"totals":{"changed_files":0,"errors":0},"file_diffs":[]}',
            errorOutput: '',
            timedOut: false,
        ), false);

        $this->assertSame(0, $result->errorCount);
        $this->assertSame([], $result->errors);
        $this->assertFalse($result->preview);
    }
}
