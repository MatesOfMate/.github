<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\PHPUnitExtension\Formatter;

use MatesOfMate\PHPUnitExtension\Grouping\FailureGrouper;
use MatesOfMate\PHPUnitExtension\Grouping\MessageStripper;
use MatesOfMate\PHPUnitExtension\Parser\TestResult;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * Formats test results for compact tool responses.
 *
 * The response leads with grouped failures rather than a list of every failing
 * test. One broken method produces one failure per test that touches it, and
 * seventeen copies of the same assertion diff cost the agent a large response
 * to learn a single fact.
 *
 * The encoder pads every column to its widest cell and rules the table at that
 * width, so the response is roughly five times the largest value in it. Keeping
 * the largest value small therefore matters about five times more than it looks.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ToonFormatter
{
    private const TESTS_SHOWN = 5;

    public function __construct(
        private readonly FailureGrouper $grouper = new FailureGrouper(),
        private readonly MessageStripper $stripper = new MessageStripper(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>>|null $groups Pre-computed groups. The caller that stores the run
     *                                                      already has them and passes them in so the grouping
     *                                                      is not repeated; anyone else gets them computed here
     *                                                      rather than getting a response with the failures
     *                                                      silently missing.
     */
    public function format(TestResult $result, string $mode = 'default', ?string $runId = null, ?array $groups = null): string
    {
        return match ($mode) {
            'default' => $this->formatDefault($result, $runId, $groups ?? $this->groupsOf($result)),
            'summary' => $this->formatSummary($result),
            'detailed' => $this->formatDetailed($result, $runId, $groups ?? $this->groupsOf($result)),
            default => throw new \InvalidArgumentException("Unknown format mode: {$mode}"),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $groups
     */
    private function formatDefault(TestResult $result, ?string $runId, array $groups): string
    {
        $data = [
            'summary' => $this->summaryOf($result),
            'status' => $result->wasSuccessful() ? 'OK' : 'FAILED',
        ];

        if ([] === $groups) {
            return ResponseEncoder::encode($data);
        }

        $data['groups'] = array_map(
            fn (array $g): array => [
                'id' => $g['id'],
                'count' => $g['count'],
                'type' => $g['type'],
                'summary' => $this->firstLine((string) $g['summary']),
                'example' => $g['tests'][0] ?? '',
            ],
            $groups
        );

        // Without a run id there is nothing to look the detail up by, so the
        // pointer is omitted rather than offered with an empty --id.
        if (null !== $runId) {
            $data['run'] = $runId;
            $data['next'] = \sprintf(
                'phpunit-run-detail --id=%s [--group=g1|--test=Class::method] for the full messages',
                $runId
            );
        }

        return ResponseEncoder::encode($data);
    }

    private function formatSummary(TestResult $result): string
    {
        return ResponseEncoder::encode([
            'tests' => $result->getTests(),
            'passed' => $result->getPassed(),
            'failed' => $result->getFailed(),
            'errors' => $result->getErrors(),
            'time' => round($result->getTime(), 3).'s',
            'status' => $result->wasSuccessful() ? 'OK' : 'FAILED',
        ]);
    }

    /**
     * Detailed adds one worked example per group and the test-to-group map. It
     * does not repeat the message once per test: that is the cost the grouping
     * exists to remove.
     *
     * @param array<int, array<string, mixed>> $groups
     */
    private function formatDetailed(TestResult $result, ?string $runId, array $groups): string
    {
        $data = [
            'summary' => $this->summaryOf($result),
            'status' => $result->wasSuccessful() ? 'OK' : 'FAILED',
        ];

        if ([] === $groups) {
            return ResponseEncoder::encode($data);
        }

        $data['groups'] = array_map(
            function (array $g): array {
                $rep = $g['representative'];

                return [
                    'id' => $g['id'],
                    'count' => $g['count'],
                    'type' => $g['type'],
                    'example' => $g['tests'][0] ?? '',
                    // detailed keeps the full path; default shortens it. That
                    // distinction predates grouping and is why the mode exists.
                    'file' => (string) ($rep['file'] ?? ''),
                    'line' => $rep['line'] ?? null,
                    'message' => $this->stripper->strip((string) ($rep['message'] ?? '')),
                    'tests' => $this->sampleTests($g['tests']),
                ];
            },
            $groups
        );
        if (null !== $runId) {
            $data['run'] = $runId;
            $data['next'] = \sprintf(
                'phpunit-run-detail --id=%s --test=Class::method for one test in full',
                $runId
            );
        }

        return ResponseEncoder::encode($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryOf(TestResult $result): array
    {
        return [
            'tests' => $result->getTests(),
            'passed' => $result->getPassed(),
            'failed' => $result->getFailed(),
            'errors' => $result->getErrors(),
            'warnings' => $result->getWarnings(),
            'skipped' => $result->getSkipped(),
            'time' => round($result->getTime(), 3).'s',
        ];
    }

    private function firstLine(string $message): string
    {
        $line = strtok($message, "\n");

        return false === $line ? '' : $line;
    }

    /**
     * A group with two hundred members must not print two hundred test names:
     * the point of the group is that the members are interchangeable, and the
     * detail tool can list them in full when that is actually wanted.
     *
     * @param array<int, string> $tests
     *
     * @return array<int, string>
     */
    private function sampleTests(array $tests): array
    {
        if (\count($tests) <= self::TESTS_SHOWN) {
            return $tests;
        }

        $shown = \array_slice($tests, 0, self::TESTS_SHOWN);
        $shown[] = \sprintf('... and %d more', \count($tests) - self::TESTS_SHOWN);

        return $shown;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupsOf(TestResult $result): array
    {
        return $this->grouper->group(array_merge($result->failures, $result->errors));
    }
}
