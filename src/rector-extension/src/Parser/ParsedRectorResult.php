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
 * Structured Rector execution result for tool responses.
 *
 * @internal
 *
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class ParsedRectorResult
{
    /**
     * @param array<int, string>                                           $changedFiles
     * @param array<int, string>                                           $rules
     * @param array<int, array<string>>                                    $diffs
     * @param array<int, array{message: string, file: string, line: ?int}> $errors
     * @param array<int, string>                                           $diagnostics
     * @param array<string, mixed>                                         $rejectedInput
     */
    public function __construct(
        public readonly bool $preview,
        public readonly int $exitCode,
        public readonly bool $timedOut,
        public readonly int $changedFileCount,
        public readonly array $changedFiles,
        public readonly array $rules,
        public readonly array $diffs,
        public readonly int $errorCount,
        public readonly array $errors,
        public readonly string $rawOutput,
        public readonly string $errorOutput,
        public readonly array $diagnostics,
        public readonly ?RunResult $runResult = null,
        public readonly array $rejectedInput = [],
    ) {
    }

    /**
     * @param array<int, string>   $diagnostics
     * @param array<string, mixed> $rejectedInput
     */
    public static function validationFailure(bool $preview, array $diagnostics, array $rejectedInput): self
    {
        return new self(
            preview: $preview,
            exitCode: 1,
            timedOut: false,
            changedFileCount: 0,
            changedFiles: [],
            rules: [],
            diffs: [],
            errorCount: 0,
            errors: [],
            rawOutput: '',
            errorOutput: '',
            diagnostics: $diagnostics,
            rejectedInput: $rejectedInput,
        );
    }
}
