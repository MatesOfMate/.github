<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Parser;

use MatesOfMate\RectorExtension\Runner\RunResult;

/**
 * Parses Rector JSON output while preserving raw output on fallback.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class RectorOutputParser
{
    public function parse(RunResult $runResult, bool $preview): ParsedRectorResult
    {
        try {
            $data = json_decode($runResult->output, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new ParsedRectorResult(
                preview: $preview,
                exitCode: $runResult->exitCode,
                timedOut: $runResult->timedOut,
                changedFileCount: 0,
                changedFiles: [],
                rules: [],
                diffs: [],
                errorCount: 0,
                errors: [],
                rawOutput: $runResult->output,
                errorOutput: $runResult->errorOutput,
                diagnostics: ['Could not parse Rector JSON output; raw output is included.'],
                runResult: $runResult,
            );
        }

        if (!\is_array($data)) {
            return new ParsedRectorResult(
                preview: $preview,
                exitCode: $runResult->exitCode,
                timedOut: $runResult->timedOut,
                changedFileCount: 0,
                changedFiles: [],
                rules: [],
                diffs: [],
                errorCount: 0,
                errors: [],
                rawOutput: $runResult->output,
                errorOutput: $runResult->errorOutput,
                diagnostics: ['Rector JSON output did not contain an object.'],
                runResult: $runResult,
            );
        }

        $changedFiles = [];
        $rules = [];
        $diffs = [];

        foreach ($data['file_diffs'] ?? [] as $fileDiff) {
            if (!\is_array($fileDiff)) {
                continue;
            }

            $file = (string) ($fileDiff['file'] ?? $fileDiff['relative_file_path'] ?? '');
            if ('' !== $file) {
                $changedFiles[] = $file;
            }

            if (isset($fileDiff['diff'])) {
                $diffs[] = [
                    'file' => $file,
                    'diff' => (string) $fileDiff['diff'],
                ];
            }

            foreach ($fileDiff['applied_rectors'] ?? $fileDiff['applied_rectors_with_changelog'] ?? [] as $rule) {
                if (\is_string($rule)) {
                    $rules[] = $rule;
                } elseif (\is_array($rule) && isset($rule['class'])) {
                    $rules[] = (string) $rule['class'];
                }
            }
        }

        $changedFileCount = (int) ($data['totals']['changed_files'] ?? \count(array_unique($changedFiles)));
        $errors = $this->parseErrors($data);
        $errorCount = (int) ($data['totals']['errors'] ?? \count($errors));

        return new ParsedRectorResult(
            preview: $preview,
            exitCode: $runResult->exitCode,
            timedOut: $runResult->timedOut,
            changedFileCount: $changedFileCount,
            changedFiles: array_values(array_unique($changedFiles)),
            rules: array_values(array_unique($rules)),
            diffs: $diffs,
            errorCount: $errorCount,
            errors: $errors,
            rawOutput: $runResult->output,
            errorOutput: $runResult->errorOutput,
            diagnostics: [],
            runResult: $runResult,
        );
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<int, array{message: string, file: string, line: ?int}>
     */
    private function parseErrors(array $data): array
    {
        $errors = [];

        foreach ($data['errors'] ?? [] as $error) {
            if (!\is_array($error)) {
                continue;
            }

            $errors[] = [
                'message' => (string) ($error['message'] ?? ''),
                'file' => (string) ($error['file'] ?? $error['relative_file_path'] ?? ''),
                'line' => isset($error['line']) ? (int) $error['line'] : null,
            ];
        }

        return $errors;
    }
}
