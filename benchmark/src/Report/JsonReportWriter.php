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

use MatesOfMate\Benchmark\Runner\CommandResult;
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

    /**
     * The excerpt keeps `results.json` reviewable; the full assistant stdout
     * is persisted separately under `raw/` by the {@see ArtifactsWriter}.
     */
    private const int RESPONSE_EXCERPT_LENGTH = 2000;

    private const int TOOL_CALL_LIMIT = 50;

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
            'scenarios' => array_map($this->scenarioPayload(...), $context->outcomes),
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
        $invalid = 0;
        $sumScore = 0.0;

        foreach ($context->outcomes as $outcome) {
            match ($outcome->status) {
                RunStatus::Passed => ++$passed,
                RunStatus::Failed => ++$failed,
                RunStatus::AdapterError, RunStatus::SetupError => ++$errors,
                RunStatus::InvalidScenario => ++$invalid,
            };
            $sumScore += $outcome->score->finalScore;
        }

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'errors' => $errors,
            'invalid_scenarios' => $invalid,
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
                'effective_weights' => $outcome->score->effectiveWeights,
                'not_applicable' => $outcome->score->notApplicable,
                'missing_evaluators' => $outcome->score->missingEvaluators,
                'gate_penalties' => $outcome->score->gatePenalties,
            ],
            'evaluations' => array_map(
                static fn (\MatesOfMate\Benchmark\Evaluator\EvaluationResult $e): array => [
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
            'baseline_red' => [
                'commands' => \count($outcome->baselineRedResults),
                'all_failed_as_expected' => $this->allFailedAsExpected($outcome->baselineRedResults),
            ],
            'diff' => $diff instanceof \MatesOfMate\Benchmark\Runner\DiffResult ? [
                'files_changed' => $diff->changedFiles,
                'additions' => $diff->additions,
                'deletions' => $diff->deletions,
            ] : null,
            'assistant' => $assistant instanceof \MatesOfMate\Benchmark\Adapter\AssistantRunResult ? [
                'successful' => $assistant->successful,
                'exit_code' => $assistant->exitCode,
                'duration_ms' => $assistant->durationMs,
                'timed_out' => $assistant->timedOut,
                'error_message' => $assistant->errorMessage,
                'response_excerpt' => '' === $assistant->stdout ? null : mb_substr($assistant->stdout, 0, self::RESPONSE_EXCERPT_LENGTH),
                'tool_calls' => array_map(
                    static fn (\MatesOfMate\Benchmark\Adapter\ToolCall $call): array => [
                        'name' => $call->name,
                        'mcp' => $call->mcp,
                    ],
                    \array_slice($assistant->toolCalls, 0, self::TOOL_CALL_LIMIT),
                ),
            ] : null,
        ];
    }

    /**
     * A scenario is only valid when every pass command was still red before
     * the assistant ran (see {@see RunStatus::InvalidScenario}).
     *
     * @param list<CommandResult> $results
     */
    private function allFailedAsExpected(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->successful()) {
                return false;
            }
        }

        return true;
    }
}
