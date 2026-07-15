<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Report;

use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Adapter\TokenUsage;
use MatesOfMate\Benchmark\Mate\MateMetrics;
use MatesOfMate\Benchmark\Report\MarkdownReportWriter;
use MatesOfMate\Benchmark\Report\ReportContext;
use MatesOfMate\Benchmark\Runner\CommandResult;
use MatesOfMate\Benchmark\Runner\DiffResult;
use MatesOfMate\Benchmark\Runner\RunOutcome;
use MatesOfMate\Benchmark\Runner\RunStatus;
use MatesOfMate\Benchmark\Scoring\Score;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MarkdownReportWriterTest extends TestCase
{
    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-md-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testRendersEverySection(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: 'ok',
                durationMs: 1.0,
                tokenUsage: new TokenUsage(50, 70, 30, 0.12),
            ),
            diff: new DiffResult(diff: 'some-diff', stat: '', changedFiles: ['x.php'], additions: 2, deletions: 1),
            mateMetrics: new MateMetrics(true, 1, ['x'], 1500.0, 0),
            verificationResults: [RunOutcomeBuilder::passingCommand()],
        )->withEvaluations([], new Score(
            finalScore: 4.0,
            rawScore: 4.0,
            perCategory: [
                'functional' => 5.0,
                'root_cause' => 3.0,
                'mate_tool_usage' => null,
                'minimality' => 4.0,
                'verification' => 5.0,
                'efficiency' => 2.0,
            ],
            weights: [],
            notApplicable: ['mate_tool_usage'],
            effectiveWeights: [],
        ));

        $failed = RunOutcomeBuilder::build(status: RunStatus::Failed);

        $context = new ReportContext(
            runId: 'run-md',
            reportDirectory: $this->tmp,
            adapter: 'null',
            mateEnabled: true,
            model: 'sonnet',
            repeat: 2,
            outcomes: [$outcome, $failed],
            startedAt: new \DateTimeImmutable('2026-04-27 20:00:00'),
            finishedAt: new \DateTimeImmutable('2026-04-27 20:00:05'),
        );

        (new MarkdownReportWriter())->write($context);

        $markdown = file_get_contents($this->tmp.'/summary.md');
        $this->assertNotFalse($markdown);

        foreach ([
            '# Benchmark run `run-md`',
            '## Summary',
            '## Adapter comparison',
            '## Mate enabled vs disabled',
            '## Scenario results',
            '## Tool usage',
            '## Token usage',
            '## Slowest runs',
            '## Failed scenarios',
            '## Most changed files',
        ] as $heading) {
            $this->assertStringContainsString($heading, $markdown);
        }

        $this->assertStringContainsString('eval.test', $markdown);
        $this->assertStringContainsString('attempt', $markdown);
        $this->assertStringContainsString('diffs/', $markdown);
        $this->assertStringContainsString('logs/', $markdown);

        // Per-category columns: numeric where scored, '–' where not applicable.
        $this->assertStringContainsString(
            '| Scenario | Attempt | Status | Score | Functional | Root cause | Mate tools | Minimality | Verification | Efficiency | Cost | Duration | Files | Error | Artefacts |',
            $markdown,
        );
        $this->assertStringContainsString('| 4.00 | 5.0 | 3.0 | – | 4.0 | 5.0 | 2.0 | $0.1200 |', $markdown);
    }

    public function testSummaryReportsPassRateAndSplitAverages(): void
    {
        $passed = RunOutcomeBuilder::build()->withEvaluations([], new Score(4.0, 4.0, [], []));
        $failed = RunOutcomeBuilder::build(status: RunStatus::Failed)->withEvaluations([], new Score(1.0, 1.0, [], []));
        // Errors and invalid scenarios carry a zero score but must not drag
        // the averages down.
        $error = RunOutcomeBuilder::build(status: RunStatus::AdapterError);
        $invalid = RunOutcomeBuilder::build(status: RunStatus::InvalidScenario);

        (new MarkdownReportWriter())->write($this->context([$passed, $failed, $error, $invalid]));

        $markdown = (string) file_get_contents($this->tmp.'/summary.md');

        $this->assertStringContainsString(
            '| Total | Passed | Failed | Errors | Invalid scenarios | Pass rate | Avg score (scored runs) | Avg score (passed runs) |',
            $markdown,
        );
        // 1 of 4 passed; scored avg = (4.0 + 1.0) / 2; passed avg = 4.0.
        $this->assertStringContainsString('| 4 | 1 | 1 | 1 | 1 | 25.0% | 2.50 | 4.00 |', $markdown);
        $this->assertStringContainsString('invalid_scenario', $markdown);
    }

    public function testTokenUsageSeparatesFreshFromCachedAndReportsCost(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: 'ok',
                durationMs: 1.0,
                tokenUsage: new TokenUsage(100, 40, 9000, 0.25),
            ),
        );

        (new MarkdownReportWriter())->write($this->context([$outcome]));

        $markdown = (string) file_get_contents($this->tmp.'/summary.md');

        $this->assertStringContainsString('| fresh input_tokens | 100 |', $markdown);
        $this->assertStringContainsString('| output_tokens | 40 |', $markdown);
        $this->assertStringContainsString('| fresh_tokens (input + output) | 140 |', $markdown);
        $this->assertStringContainsString('| cached_tokens (cache reads, billed at a fraction) | 9000 |', $markdown);
        $this->assertStringContainsString('| cost_usd | $0.2500 |', $markdown);
        $this->assertStringNotContainsString('total_tokens', $markdown);
    }

    public function testCostRowIsOmittedWhenNoAdapterReportsCost(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: 'ok',
                durationMs: 1.0,
                tokenUsage: new TokenUsage(10, 20),
            ),
        );

        (new MarkdownReportWriter())->write($this->context([$outcome]));

        $markdown = (string) file_get_contents($this->tmp.'/summary.md');

        $this->assertStringNotContainsString('cost_usd', $markdown);
    }

    public function testDegenerateMetricsAreNotRendered(): void
    {
        $outcome = RunOutcomeBuilder::build(metricsOverrides: [
            'time_to_first_tool_call_ms' => 12.0,
            'time_to_first_code_change_ms' => 34.0,
            'redundant_tool_call_count' => 5,
        ]);

        (new MarkdownReportWriter())->write($this->context([$outcome]));

        $markdown = (string) file_get_contents($this->tmp.'/summary.md');

        $this->assertStringNotContainsString('time_to_first_tool_call_ms', $markdown);
        $this->assertStringNotContainsString('time_to_first_code_change_ms', $markdown);
        $this->assertStringNotContainsString('redundant_tool_call_count', $markdown);
    }

    public function testFailedScenariosIncludeErrorAndFailedPassCommand(): void
    {
        $failedCommand = new CommandResult(
            command: 'php tests/test.php',
            cwd: '/tmp/workspace',
            exitCode: 2,
            stdout: '',
            stderr: "Fatal error: something broke\nin file.php on line 3",
            durationMs: 5.0,
            timedOut: false,
        );

        $outcome = $this->withError(
            RunOutcomeBuilder::build(
                verificationResults: [RunOutcomeBuilder::passingCommand(), $failedCommand],
                status: RunStatus::Failed,
            ),
            'Verification failed after the assistant run.',
        );

        (new MarkdownReportWriter())->write($this->context([$outcome]));

        $markdown = (string) file_get_contents($this->tmp.'/summary.md');

        $this->assertStringContainsString('| Scenario | Attempt | Status | Error | Failed pass command |', $markdown);
        $this->assertStringContainsString('Verification failed after the assistant run.', $markdown);
        $this->assertStringContainsString('`php tests/test.php` (exit 2): Fatal error: something broke in file.php on line 3', $markdown);
    }

    public function testErrorColumnTruncatesLongMessagesForNonPassedRuns(): void
    {
        $outcome = $this->withError(
            RunOutcomeBuilder::build(status: RunStatus::AdapterError),
            str_repeat('x', 200),
        );

        (new MarkdownReportWriter())->write($this->context([$outcome]));

        $markdown = (string) file_get_contents($this->tmp.'/summary.md');

        // Scenario table truncates at ~80 chars; the failed-scenarios table
        // allows a longer excerpt but never the full 200 characters.
        $this->assertStringContainsString(str_repeat('x', 80).'…', $markdown);
        $this->assertStringNotContainsString(str_repeat('x', 161), $markdown);
        $this->assertStringContainsString('adapter_error', $markdown);
    }

    public function testEmptyDiffDoesNotProduceADiffLink(): void
    {
        $outcome = RunOutcomeBuilder::build(
            diff: new DiffResult(diff: '', stat: '', changedFiles: [], additions: 0, deletions: 0),
        );

        (new MarkdownReportWriter())->write($this->context([$outcome]));

        $markdown = (string) file_get_contents($this->tmp.'/summary.md');

        $this->assertStringNotContainsString('diffs/', $markdown);
        $this->assertStringContainsString('[log](logs/eval.test-attempt-1.log)', $markdown);
    }

    public function testInvalidScenarioStatusIsRenderedExplicitly(): void
    {
        $outcome = $this->withError(
            RunOutcomeBuilder::build(status: RunStatus::InvalidScenario),
            'All pass_commands already succeed before the assistant ran.',
        );

        (new MarkdownReportWriter())->write($this->context([$outcome]));

        $markdown = (string) file_get_contents($this->tmp.'/summary.md');

        $this->assertStringContainsString('invalid_scenario', $markdown);
        $this->assertStringContainsString('All pass_commands already succeed before the assistant ran.', $markdown);
    }

    public function testFallbackTextWhenNoToolUsageOrTokens(): void
    {
        $context = new ReportContext(
            runId: 'empty',
            reportDirectory: $this->tmp,
            adapter: 'null',
            mateEnabled: false,
            model: null,
            repeat: 1,
            outcomes: [RunOutcomeBuilder::build()],
            startedAt: new \DateTimeImmutable('now'),
            finishedAt: new \DateTimeImmutable('now'),
        );

        (new MarkdownReportWriter())->write($context);
        $markdown = file_get_contents($this->tmp.'/summary.md');
        $this->assertNotFalse($markdown);
        $this->assertStringContainsString('_no tool calls observed_', $markdown);
        $this->assertStringContainsString('_no token data reported_', $markdown);
    }

    /**
     * @param list<RunOutcome> $outcomes
     */
    private function context(array $outcomes): ReportContext
    {
        return new ReportContext(
            runId: 'run-md',
            reportDirectory: $this->tmp,
            adapter: 'null',
            mateEnabled: true,
            model: null,
            repeat: 1,
            outcomes: $outcomes,
            startedAt: new \DateTimeImmutable('2026-04-27 20:00:00'),
            finishedAt: new \DateTimeImmutable('2026-04-27 20:00:05'),
        );
    }

    /**
     * Clones an outcome with an error message attached.
     */
    private function withError(RunOutcome $outcome, string $errorMessage): RunOutcome
    {
        return new RunOutcome(
            scenario: $outcome->scenario,
            workspace: $outcome->workspace,
            status: $outcome->status,
            setupResults: $outcome->setupResults,
            baselineResults: $outcome->baselineResults,
            assistantResult: $outcome->assistantResult,
            diff: $outcome->diff,
            verificationResults: $outcome->verificationResults,
            mateMetrics: $outcome->mateMetrics,
            metrics: $outcome->metrics,
            totalDurationMs: $outcome->totalDurationMs,
            errorMessage: $errorMessage,
            evaluations: $outcome->evaluations,
            score: $outcome->score,
            baselineRedResults: $outcome->baselineRedResults,
        );
    }
}
