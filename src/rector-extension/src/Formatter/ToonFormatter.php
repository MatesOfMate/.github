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
use MatesOfMate\RectorExtension\Parser\ParsedRectorResult;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * Formats Rector workflow results for compact MCP responses.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ToonFormatter
{
    public function formatInspection(ProjectContext $context): string
    {
        return ResponseEncoder::encode($context->toArray());
    }

    public function format(ParsedRectorResult $result, string $mode = 'default'): string
    {
        return match ($mode) {
            'default' => $this->formatDefault($result),
            'summary' => $this->formatSummary($result),
            'detailed' => $this->formatDetailed($result),
            default => throw new \InvalidArgumentException("Unknown format mode: {$mode}"),
        };
    }

    private function formatDefault(ParsedRectorResult $result): string
    {
        $data = $this->summary($result);

        if ([] !== $result->changedFiles) {
            $data['changed_files'] = $result->changedFiles;
        }

        if ([] !== $result->rules) {
            $data['rules'] = $result->rules;
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

        if ([] !== $result->diagnostics) {
            $data['diagnostics'] = $result->diagnostics;
        }

        if ([] !== $result->rejectedInput) {
            $data['rejected_input'] = $result->rejectedInput;
        }

        return ResponseEncoder::encode($data);
    }

    private function formatDetailed(ParsedRectorResult $result): string
    {
        $data = $this->summary($result);
        $data['changed_files'] = $result->changedFiles;
        $data['rules'] = $result->rules;
        $data['diffs'] = $result->diffs;
        $data['raw_output'] = $result->rawOutput;
        $data['error_output'] = $result->errorOutput;
        $data['diagnostics'] = $result->diagnostics;
        $data['rejected_input'] = $result->rejectedInput;

        if ($result->runResult instanceof \MatesOfMate\RectorExtension\Runner\RunResult) {
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
        return [
            'workflow' => $result->preview ? 'preview' : 'apply',
            'status' => 0 === $result->exitCode ? 'SUCCESS' : 'FAILED',
            'exit_code' => $result->exitCode,
            'timed_out' => $result->timedOut,
            'changed_file_count' => $result->changedFileCount,
            'change_count' => $result->changedFileCount,
        ];
    }
}
