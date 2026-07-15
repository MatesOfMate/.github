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
use MatesOfMate\Benchmark\Adapter\ToolCall;
use MatesOfMate\Benchmark\Evaluator\EvaluationResult;
use MatesOfMate\Benchmark\Mate\MateMetrics;
use MatesOfMate\Benchmark\Report\JsonReportWriter;
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
class JsonReportWriterTest extends TestCase
{
    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-json-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testWritesValidJsonWithExpectedShape(): void
    {
        $outcome = $this->withBaselineRed(
            RunOutcomeBuilder::build(
                assistantResult: AssistantRunResult::success(
                    stdout: 'ok',
                    durationMs: 1234.0,
                    tokenUsage: new TokenUsage(100, 200),
                    toolCalls: [
                        new ToolCall('mate-phpunit-run', mcp: true),
                        new ToolCall('shell'),
                    ],
                ),
                diff: new DiffResult(diff: '', stat: '', changedFiles: ['a.php'], additions: 4, deletions: 1),
                mateMetrics: new MateMetrics(true, 2, ['x'], 1500.0, 0),
                verificationResults: [RunOutcomeBuilder::passingCommand()],
            ),
            [RunOutcomeBuilder::failingCommand()],
        )->withEvaluations(
            [new EvaluationResult('functional', 5.0, true, 'all good')],
            new Score(
                finalScore: 4.5,
                rawScore: 4.5,
                perCategory: ['functional' => 5.0, 'mate_tool_usage' => null],
                weights: ['functional' => 0.85, 'mate_tool_usage' => 0.15],
                notApplicable: ['mate_tool_usage'],
                effectiveWeights: ['functional' => 1.0],
            ),
        );

        $context = new ReportContext(
            runId: 'run-test',
            reportDirectory: $this->tmp,
            adapter: 'null',
            mateEnabled: true,
            model: 'mock',
            repeat: 1,
            outcomes: [$outcome],
            startedAt: new \DateTimeImmutable('2026-04-27 20:00:00'),
            finishedAt: new \DateTimeImmutable('2026-04-27 20:00:05'),
        );

        (new JsonReportWriter())->write($context);

        $payload = json_decode((string) file_get_contents($this->tmp.'/results.json'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame('run-test', $payload['run_id']);
        $this->assertSame('null', $payload['adapter']);
        $this->assertTrue($payload['mate_enabled']);
        $this->assertSame(1, $payload['summary']['total']);
        $this->assertSame(1, $payload['summary']['passed']);
        $this->assertSame(0, $payload['summary']['invalid_scenarios']);
        $this->assertSame(4.5, $payload['summary']['average_score']);
        $this->assertSame('eval.test', $payload['scenarios'][0]['id']);
        $this->assertSame(4.5, $payload['scenarios'][0]['score']['final']);
        $this->assertEqualsCanonicalizing(['functional' => 5.0, 'mate_tool_usage' => null], $payload['scenarios'][0]['score']['per_category']);
        $this->assertSame(['mate_tool_usage'], $payload['scenarios'][0]['score']['not_applicable']);
        $this->assertEquals(['functional' => 1.0], $payload['scenarios'][0]['score']['effective_weights']);
        $this->assertSame(['a.php'], $payload['scenarios'][0]['diff']['files_changed']);
        $this->assertSame('ok', $payload['scenarios'][0]['assistant']['response_excerpt']);
        $this->assertSame(
            [
                ['name' => 'mate-phpunit-run', 'mcp' => true],
                ['name' => 'shell', 'mcp' => false],
            ],
            $payload['scenarios'][0]['assistant']['tool_calls'],
        );
        $this->assertSame(1, $payload['scenarios'][0]['baseline_red']['commands']);
        $this->assertTrue($payload['scenarios'][0]['baseline_red']['all_failed_as_expected']);
    }

    public function testResponseExcerptIsCappedAtTwoThousandCharacters(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: str_repeat('a', 2500),
                durationMs: 1.0,
            ),
        );

        (new JsonReportWriter())->write($this->context([$outcome]));

        $payload = json_decode((string) file_get_contents($this->tmp.'/results.json'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(2000, mb_strlen((string) $payload['scenarios'][0]['assistant']['response_excerpt']));
    }

    public function testEmptyStdoutProducesNullResponseExcerpt(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::failure('boom'),
            status: RunStatus::AdapterError,
        );

        (new JsonReportWriter())->write($this->context([$outcome]));

        $payload = json_decode((string) file_get_contents($this->tmp.'/results.json'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertNull($payload['scenarios'][0]['assistant']['response_excerpt']);
    }

    public function testToolCallsAreCappedAtFiftyEntries(): void
    {
        $toolCalls = [];
        for ($i = 0; $i < 55; ++$i) {
            $toolCalls[] = new ToolCall('tool-'.$i, arguments: ['huge' => str_repeat('x', 100)]);
        }

        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: 'ok',
                durationMs: 1.0,
                toolCalls: $toolCalls,
            ),
        );

        (new JsonReportWriter())->write($this->context([$outcome]));

        $payload = json_decode((string) file_get_contents($this->tmp.'/results.json'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertCount(50, $payload['scenarios'][0]['assistant']['tool_calls']);
        $this->assertSame(['name' => 'tool-0', 'mcp' => false], $payload['scenarios'][0]['assistant']['tool_calls'][0]);
        $this->assertArrayNotHasKey('arguments', $payload['scenarios'][0]['assistant']['tool_calls'][0]);
    }

    public function testBaselineRedWithSucceedingCommandIsNotFlaggedAsExpected(): void
    {
        $outcome = $this->withBaselineRed(
            RunOutcomeBuilder::build(status: RunStatus::InvalidScenario),
            [RunOutcomeBuilder::passingCommand(), RunOutcomeBuilder::failingCommand()],
        );

        (new JsonReportWriter())->write($this->context([$outcome]));

        $payload = json_decode((string) file_get_contents($this->tmp.'/results.json'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(2, $payload['scenarios'][0]['baseline_red']['commands']);
        $this->assertFalse($payload['scenarios'][0]['baseline_red']['all_failed_as_expected']);
        $this->assertSame('invalid_scenario', $payload['scenarios'][0]['status']);
        $this->assertSame(1, $payload['summary']['invalid_scenarios']);
        $this->assertSame(0, $payload['summary']['errors']);
    }

    public function testEmptyOutcomesProduceEmptyScenariosArray(): void
    {
        $context = new ReportContext(
            runId: 'empty',
            reportDirectory: $this->tmp,
            adapter: 'null',
            mateEnabled: false,
            model: null,
            repeat: 1,
            outcomes: [],
            startedAt: new \DateTimeImmutable('now'),
            finishedAt: new \DateTimeImmutable('now'),
        );

        (new JsonReportWriter())->write($context);

        $payload = json_decode((string) file_get_contents($this->tmp.'/results.json'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame([], $payload['scenarios']);
        $this->assertSame(0, $payload['summary']['total']);
    }

    public function testStatusValuesUseStringRepresentation(): void
    {
        $outcome = RunOutcomeBuilder::build(status: RunStatus::AdapterError);
        $context = new ReportContext(
            runId: 'err',
            reportDirectory: $this->tmp,
            adapter: 'null',
            mateEnabled: false,
            model: null,
            repeat: 1,
            outcomes: [$outcome],
            startedAt: new \DateTimeImmutable('now'),
            finishedAt: new \DateTimeImmutable('now'),
        );

        (new JsonReportWriter())->write($context);

        $payload = json_decode((string) file_get_contents($this->tmp.'/results.json'), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('adapter_error', $payload['scenarios'][0]['status']);
    }

    /**
     * @param list<RunOutcome> $outcomes
     */
    private function context(array $outcomes): ReportContext
    {
        return new ReportContext(
            runId: 'run-test',
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
     * Clones an outcome with baseline red-check results attached.
     *
     * @param list<CommandResult> $baselineRedResults
     */
    private function withBaselineRed(RunOutcome $outcome, array $baselineRedResults): RunOutcome
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
            errorMessage: $outcome->errorMessage,
            evaluations: $outcome->evaluations,
            score: $outcome->score,
            baselineRedResults: $baselineRedResults,
        );
    }
}
