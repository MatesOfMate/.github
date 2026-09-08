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
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
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
        $ruleFiles = [];
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
                $class = null;
                if (\is_string($rule)) {
                    $class = $rule;
                } elseif (\is_array($rule) && isset($rule['class'])) {
                    $class = (string) $rule['class'];
                }

                if (null === $class) {
                    continue;
                }

                $rules[] = $class;
                // Rector says which rules changed which file. Keeping only the
                // flat list of rule names throws that away, and it is the one
                // thing that turns a wall of diffs into "this rule touched
                // twelve files".
                $ruleFiles[$class][] = $file;
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
            ruleFiles: array_map(static fn (array $files): array => array_values(array_unique($files)), $ruleFiles),
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
