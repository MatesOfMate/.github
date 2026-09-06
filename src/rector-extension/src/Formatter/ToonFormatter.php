<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Formatter;

use MatesOfMate\RectorExtension\Discovery\ProjectContext;
use MatesOfMate\RectorExtension\Grouping\RuleGrouper;
use MatesOfMate\RectorExtension\Parser\ParsedRectorResult;
use MatesOfMate\RectorExtension\Runner\RunResult;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * Formats Rector workflow results for compact tool responses.
 *
 * @internal
 *
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class ToonFormatter
{
    private const FILES_SHOWN = 5;

    private const EXIT_SUCCESS = 0;

    /**
     * Rector exits with this code when it found code to change, which is the
     * successful outcome of a `--dry-run` preview.
     */
    private const EXIT_CHANGED_CODE = 2;

    public function __construct(
        private readonly RuleGrouper $grouper = new RuleGrouper(),
    ) {
    }

    public function formatInspection(ProjectContext $context): string
    {
        return ResponseEncoder::encode($context->toArray());
    }

    public function format(ParsedRectorResult $result, string $mode = 'default', ?string $runId = null): string
    {
        return match ($mode) {
            'default' => $this->formatDefault($result, $runId),
            'summary' => $this->formatSummary($result),
            'detailed' => $this->formatDetailed($result, $runId),
            default => throw new \InvalidArgumentException("Unknown format mode: {$mode}"),
        };
    }

    private function formatDefault(ParsedRectorResult $result, ?string $runId): string
    {
        $data = $this->summary($result);

        if ([] !== $result->changedFiles) {
            $data['changed_files'] = $result->changedFiles;
        }

        if ([] !== $result->rules) {
            $data['rules'] = $this->ruleGroups($result, withFiles: false);
        }

        $this->addRunPointer($data, $runId);

        if ([] !== $result->errors) {
            $data['errors'] = $result->errors;
        }

        if ('' !== $result->errorOutput) {
            $data['error_output'] = $result->errorOutput;
        }

        if ([] !== $result->diagnostics) {
            $data['diagnostics'] = $result->diagnostics;
        }

        if ([] !== $result->rejectedInput) {
            $data['rejected_input'] = $result->rejectedInput;
        }

        return ResponseEncoder::encode($data);
    }

    private function formatSummary(ParsedRectorResult $result): string
    {
        $data = $this->summary($result);

        if ([] !== $result->errors) {
            $data['errors'] = $result->errors;
        }

        if ([] !== $result->diagnostics) {
            $data['diagnostics'] = $result->diagnostics;
        }

        if ([] !== $result->rejectedInput) {
            $data['rejected_input'] = $result->rejectedInput;
        }

        return ResponseEncoder::encode($data);
    }

    private function formatDetailed(ParsedRectorResult $result, ?string $runId): string
    {
        $data = $this->summary($result);
        $data['changed_files'] = $result->changedFiles;
        $data['rules'] = $this->ruleGroups($result, withFiles: true);
        $data['errors'] = $result->errors;
        $data['error_output'] = $result->errorOutput;
        $data['diagnostics'] = $result->diagnostics;
        $data['rejected_input'] = $result->rejectedInput;

        // raw_output used to be included here in full. It is Rector's own JSON,
        // which is where changed_files, rules and diffs were parsed from, so it
        // repeated the whole response inside it. Being multi-line, it also cost
        // several times its own size once rendered. The diffs it carried are
        // reachable through rector-run-detail instead.
        if ([] !== $result->diagnostics && '' !== $result->rawOutput) {
            $data['raw_output'] = $result->rawOutput;
        }

        $this->addRunPointer($data, $runId);

        if ($result->runResult instanceof RunResult) {
            $data['execution'] = [
                'command' => $result->runResult->command,
                'working_directory' => $result->runResult->workingDirectory,
                'strategy' => $result->runResult->strategy,
            ];
        }

        return ResponseEncoder::encode($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ParsedRectorResult $result): array
    {
        $data = [
            'workflow' => $result->preview ? 'preview' : 'apply',
            'status' => $this->status($result),
            'exit_code' => $result->exitCode,
            'timed_out' => $result->timedOut,
            'changed_file_count' => $result->changedFileCount,
        ];

        if (0 !== $result->errorCount) {
            $data['error_count'] = $result->errorCount;
        }

        return $data;
    }

    /**
     * Rector signals pending changes with a dedicated exit code, which is the expected
     * outcome of a preview and must not be reported as a failure.
     */
    private function status(ParsedRectorResult $result): string
    {
        if ($result->timedOut) {
            return 'TIMEOUT';
        }

        if (0 !== $result->errorCount) {
            return 'FAILED';
        }

        return \in_array($result->exitCode, [self::EXIT_SUCCESS, self::EXIT_CHANGED_CODE], true) ? 'SUCCESS' : 'FAILED';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ruleGroups(ParsedRectorResult $result, bool $withFiles): array
    {
        $groups = $this->grouper->group($result->ruleFiles);

        if ([] === $groups) {
            // Nothing associated the rules with files, so report them as they
            // came rather than inventing empty groups.
            return array_map(
                fn (string $rule): array => ['rule' => $rule, 'short' => $this->grouper->shortName($rule)],
                $result->rules
            );
        }

        return array_map(
            static function (array $group) use ($withFiles): array {
                $files = $group['files'];
                if (\count($files) > self::FILES_SHOWN) {
                    $files = \array_slice($files, 0, self::FILES_SHOWN);
                    $files[] = \sprintf('... and %d more', $group['count'] - self::FILES_SHOWN);
                }

                $entry = [
                    'id' => $group['id'],
                    'short' => $group['short'],
                    'set' => $group['set'],
                    'files_changed' => $group['count'],
                ];

                // default already lists changed_files once; repeating them per
                // rule is the same information charged several times over.
                if ($withFiles) {
                    $entry['files'] = $files;
                    $entry['rule'] = $group['rule'];
                }

                return $entry;
            },
            $groups
        );
    }

    /**
     * Without a run id there is nothing to look anything up by, so the pointer
     * is omitted rather than offered with an empty --id.
     *
     * @param array<string, mixed> $data
     */
    private function addRunPointer(array &$data, ?string $runId): void
    {
        if (null === $runId) {
            return;
        }

        $data['run'] = $runId;
        $data['next'] = \sprintf('rector-run-detail --id=%s [--rule=g1|--file=path] for the diffs', $runId);
    }
}
