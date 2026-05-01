<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Report;

use MatesOfMate\Benchmark\Runner\RunOutcome;
use MatesOfMate\Benchmark\Runner\RunStatus;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Produces a deterministic, machine-readable `results.json` for one benchmark run.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class JsonReportWriter implements ReportWriterInterface
{
    public const FILENAME = 'results.json';

    private readonly Filesystem $filesystem;

    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function write(ReportContext $context): void
    {
        $payload = [
            'run_id' => $context->runId,
            'adapter' => $context->adapter,
            'mate_enabled' => $context->mateEnabled,
            'model' => $context->model,
            'repeat' => $context->repeat,
            'started_at' => $context->startedAt->format(\DATE_ATOM),
            'finished_at' => $context->finishedAt->format(\DATE_ATOM),
            'duration_seconds' => $context->durationSeconds(),
            'summary' => $this->summary($context),
            'scenarios' => array_map([$this, 'scenarioPayload'], $context->outcomes),
        ];

        $this->filesystem->mkdir($context->reportDirectory);
        $this->filesystem->dumpFile(
            rtrim($context->reportDirectory, '/').'/'.self::FILENAME,
            json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ReportContext $context): array
    {
        $total = \count($context->outcomes);
        $passed = 0;
        $failed = 0;
        $errors = 0;
        $sumScore = 0.0;

        foreach ($context->outcomes as $outcome) {
            match ($outcome->status) {
                RunStatus::Passed => ++$passed,
                RunStatus::Failed => ++$failed,
                RunStatus::AdapterError, RunStatus::SetupError => ++$errors,
            };
            $sumScore += $outcome->score->finalScore;
        }

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'errors' => $errors,
            'average_score' => 0 === $total ? 0.0 : round($sumScore / $total, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scenarioPayload(RunOutcome $outcome): array
    {
        $diff = $outcome->diff;
        $assistant = $outcome->assistantResult;

        return [
            'id' => $outcome->scenario->id,
            'suite' => $outcome->scenario->suite,
            'difficulty' => $outcome->scenario->difficulty,
            'attempt' => $outcome->workspace->attempt,
            'status' => $outcome->status->value,
            'duration_ms' => $outcome->totalDurationMs,
            'error' => $outcome->errorMessage,
            'score' => [
                'final' => $outcome->score->finalScore,
                'raw' => $outcome->score->rawScore,
                'per_category' => $outcome->score->perCategory,
                'weights' => $outcome->score->weights,
                'missing_evaluators' => $outcome->score->missingEvaluators,
                'gate_penalties' => $outcome->score->gatePenalties,
            ],
            'evaluations' => array_map(
                static fn ($e) => [
                    'name' => $e->name,
                    'score' => $e->score,
                    'passed' => $e->passed,
                    'explanation' => $e->explanation,
                    'evidence' => $e->evidence,
                ],
                $outcome->evaluations,
            ),
            'metrics' => $outcome->metrics->toArray(),
            'mate' => [
                'enabled' => $outcome->mateMetrics->enabled,
                'tool_call_count' => $outcome->mateMetrics->toolCallCount,
                'tool_names' => $outcome->mateMetrics->toolNames,
                'first_tool_call_ms' => $outcome->mateMetrics->firstToolCallMs,
                'tool_errors' => $outcome->mateMetrics->toolErrors,
                'expected_tools' => $outcome->mateMetrics->expectedTools,
                'missing_expected_tools' => $outcome->mateMetrics->missingExpectedTools,
                'expected_tools_any' => $outcome->mateMetrics->expectedToolsAny,
                'any_tool_matched' => $outcome->mateMetrics->anyToolMatched,
            ],
            'diff' => null === $diff ? null : [
                'files_changed' => $diff->changedFiles,
                'additions' => $diff->additions,
                'deletions' => $diff->deletions,
            ],
            'assistant' => null === $assistant ? null : [
                'successful' => $assistant->successful,
                'exit_code' => $assistant->exitCode,
                'duration_ms' => $assistant->durationMs,
                'timed_out' => $assistant->timedOut,
                'error_message' => $assistant->errorMessage,
                'tool_calls' => array_map(
                    static fn ($call) => [
                        'name' => $call->name,
                        'arguments' => $call->arguments,
                        'errored' => $call->errored,
                    ],
                    $assistant->toolCalls,
                ),
            ],
        ];
    }
}
