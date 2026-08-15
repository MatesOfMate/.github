<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Tests\Unit\Formatter;

use MatesOfMate\RectorExtension\Formatter\ToonFormatter;
use MatesOfMate\RectorExtension\Parser\ParsedRectorResult;
use PHPUnit\Framework\TestCase;

/**
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class ToonFormatterTest extends TestCase
{
    /**
     * Rector exits with code 2 from `--dry-run` when it found something to change,
     * which is the expected successful outcome of a preview.
     */
    public function testPreviewWithPendingChangesIsReportedAsSuccess(): void
    {
        $payload = $this->format($this->parsedResult(exitCode: 2, changedFileCount: 3, changedFiles: ['src/Foo.php']));

        $this->assertSame('SUCCESS', $payload['status']);
        $this->assertSame(2, $payload['exit_code']);
        $this->assertSame(3, $payload['changed_file_count']);
        $this->assertSame(['src/Foo.php'], $payload['changed_files']);
    }

    public function testCleanPreviewIsReportedAsSuccess(): void
    {
        $this->assertSame('SUCCESS', $this->format($this->parsedResult(exitCode: 0))['status']);
    }

    public function testRectorFailureIsReportedAsFailed(): void
    {
        $this->assertSame('FAILED', $this->format($this->parsedResult(exitCode: 1))['status']);
    }

    public function testRectorErrorsAreSurfacedAndMarkTheRunAsFailed(): void
    {
        $errors = [['message' => 'Syntax error', 'file' => 'src/Broken.php', 'line' => 2]];

        $payload = $this->format($this->parsedResult(exitCode: 0, errorCount: 1, errors: $errors));

        $this->assertSame('FAILED', $payload['status']);
        $this->assertSame(1, $payload['error_count']);
        $this->assertSame($errors, $payload['errors']);
    }

    public function testTimeoutIsReportedDistinctly(): void
    {
        $this->assertSame('TIMEOUT', $this->format($this->parsedResult(exitCode: 124, timedOut: true))['status']);
    }

    public function testSummaryOmitsRedundantChangeCounter(): void
    {
        $payload = $this->format($this->parsedResult(exitCode: 0, changedFileCount: 2), 'summary');

        $this->assertArrayHasKey('changed_file_count', $payload);
        $this->assertArrayNotHasKey('change_count', $payload);
    }

    public function testUnknownModeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown format mode: nope');

        (new ToonFormatter())->format($this->parsedResult(exitCode: 0), 'nope');
    }

    /**
     * @return array<string, mixed>
     */
    private function format(ParsedRectorResult $result, string $mode = 'default'): array
    {
        return json_decode((new ToonFormatter())->format($result, $mode), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<int, string>                                           $changedFiles
     * @param array<int, array{message: string, file: string, line: ?int}> $errors
     */
    private function parsedResult(
        int $exitCode,
        bool $timedOut = false,
        int $changedFileCount = 0,
        array $changedFiles = [],
        int $errorCount = 0,
        array $errors = [],
    ): ParsedRectorResult {
        return new ParsedRectorResult(
            preview: true,
            exitCode: $exitCode,
            timedOut: $timedOut,
            changedFileCount: $changedFileCount,
            changedFiles: $changedFiles,
            rules: [],
            diffs: [],
            errorCount: $errorCount,
            errors: $errors,
            rawOutput: '',
            errorOutput: '',
            diagnostics: [],
        );
    }
}
