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
use MatesOfMate\Benchmark\Evaluator\EvaluationResult;
use MatesOfMate\Benchmark\Mate\MateMetrics;
use MatesOfMate\Benchmark\Report\JsonReportWriter;
use MatesOfMate\Benchmark\Report\ReportContext;
use MatesOfMate\Benchmark\Runner\DiffResult;
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
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: 'ok',
                durationMs: 1234.0,
                tokenUsage: new TokenUsage(100, 200),
            ),
            diff: new DiffResult(diff: '', stat: '', changedFiles: ['a.php'], additions: 4, deletions: 1),
            mateMetrics: new MateMetrics(true, 2, ['x'], 1500.0, 0),
            verificationResults: [RunOutcomeBuilder::passingCommand()],
        )->withEvaluations(
            [new EvaluationResult('functional', 5.0, true, 'all good')],
            new Score(4.5, 4.5, ['functional' => 5.0], ['functional' => 1.0]),
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
        $this->assertSame(4.5, $payload['summary']['average_score']);
        $this->assertSame('eval.test', $payload['scenarios'][0]['id']);
        $this->assertSame(4.5, $payload['scenarios'][0]['score']['final']);
        $this->assertEqualsCanonicalizing(['functional' => 5.0], $payload['scenarios'][0]['score']['per_category']);
        $this->assertSame(['a.php'], $payload['scenarios'][0]['diff']['files_changed']);
        $this->assertSame(100, $payload['scenarios'][0]['assistant']['tool_calls'] === [] ? 100 : $payload['scenarios'][0]['metrics']['input_tokens']);
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
}
