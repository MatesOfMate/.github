<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Runner;

use MatesOfMate\Benchmark\Adapter\AssistantAdapterInterface;
use MatesOfMate\Benchmark\Adapter\AssistantRunInput;
use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Adapter\NullAdapter;
use MatesOfMate\Benchmark\Adapter\ToolCall;
use MatesOfMate\Benchmark\Evaluator\EvaluationPipeline;
use MatesOfMate\Benchmark\Mate\MateConfigurationFactory;
use MatesOfMate\Benchmark\Mate\MateMetricsCollector;
use MatesOfMate\Benchmark\Mate\MateProvisionerInterface;
use MatesOfMate\Benchmark\Metrics\MetricsAggregator;
use MatesOfMate\Benchmark\Metrics\MetricsBag;
use MatesOfMate\Benchmark\Scoring\ScoreCalculator;
use MatesOfMate\Benchmark\Runner\CommandExecutor;
use MatesOfMate\Benchmark\Runner\FixtureCopier;
use MatesOfMate\Benchmark\Runner\GitDiffCollector;
use MatesOfMate\Benchmark\Runner\RunRequest;
use MatesOfMate\Benchmark\Runner\RunStatus;
use MatesOfMate\Benchmark\Runner\ScenarioRunner;
use MatesOfMate\Benchmark\Runner\WorkspaceFactory;
use MatesOfMate\Benchmark\Scenario\Scenario;
use PHPUnit\Framework\TestCase;
use MatesOfMate\Benchmark\Tests\Fixtures\Mate\FakeMateProvisioner;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScenarioRunnerTest extends TestCase
{
    private string $tmp;

    private string $fixtureDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-runner-'.bin2hex(random_bytes(4));
        $this->fixtureDir = $this->tmp.'/fixture';
        $this->filesystem->mkdir($this->fixtureDir);
        file_put_contents($this->fixtureDir.'/app.php', '<?php echo "ok";'."\n");

        if (!(new CommandExecutor())->execute('command -v git', sys_get_temp_dir())->successful()) {
            $this->markTestSkipped('git is not available on PATH.');
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testRunsScenarioWithNullAdapter(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'Find the bug.'],
            'expected' => ['pass_commands' => ['php -r "exit(0);"']],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: new NullAdapter(),
            runId: 'run-test',
            attempt: 1,
        ));

        $this->assertSame(RunStatus::Passed, $outcome->status);
        $this->assertNotNull($outcome->assistantResult);
        $this->assertTrue($outcome->assistantResult->successful);
        $this->assertNotNull($outcome->diff);
        $this->assertSame([], $outcome->diff->changedFiles, 'NullAdapter must not change the workspace.');
        $this->assertCount(1, $outcome->verificationResults);
    }

    public function testFailingVerifyMakesOutcomeFailed(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'Find the bug.'],
            'expected' => ['pass_commands' => ['php -r "exit(2);"']],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: new NullAdapter(),
            runId: 'run-test',
        ));

        $this->assertSame(RunStatus::Failed, $outcome->status);
    }

    public function testAdapterExceptionIsCapturedNotCrashed(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'whatever'],
            'expected' => ['pass_commands' => []],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: $this->throwingAdapter('adapter exploded'),
            runId: 'run-test',
        ));

        $this->assertSame(RunStatus::AdapterError, $outcome->status);
        $this->assertNotNull($outcome->assistantResult);
        $this->assertFalse($outcome->assistantResult->successful);
        $this->assertSame('adapter exploded', $outcome->assistantResult->errorMessage);
    }

    public function testFailingSetupBecomesSetupError(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => [
                'path' => $this->fixtureDir,
                'setup' => ['php -r "exit(3);"'],
            ],
            'task' => ['prompt' => 'whatever'],
            'expected' => ['pass_commands' => []],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: new NullAdapter(),
            runId: 'run-test',
        ));

        $this->assertSame(RunStatus::SetupError, $outcome->status);
        $this->assertNotNull($outcome->errorMessage);
        $this->assertNotEmpty($outcome->setupResults);
    }

    public function testMissingFixtureBecomesSetupError(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->tmp.'/does-not-exist'],
            'task' => ['prompt' => 'whatever'],
            'expected' => ['pass_commands' => []],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: new NullAdapter(),
            runId: 'run-test',
        ));

        $this->assertSame(RunStatus::SetupError, $outcome->status);
        $this->assertNotNull($outcome->errorMessage);
    }

    public function testMateDisabledProducesEmptyMetricsAndNoConfigFile(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'whatever'],
            'expected' => ['pass_commands' => []],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: new NullAdapter(),
            runId: 'run-test',
            mateEnabled: false,
            keepWorkspace: true,
        ));

        $this->assertFalse($outcome->mateMetrics->enabled);
        $this->assertSame(0, $outcome->mateMetrics->toolCallCount);
        $this->assertFileDoesNotExist($outcome->workspace->path.'/mcp.json');
    }

    public function testMateEnabledWritesConfigAndAggregatesToolCalls(): void
    {
        $runner = $this->createRunner(new FakeMateProvisioner());
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'use mate'],
            'expected' => [
                'pass_commands' => [],
                'expected_tool_calls' => ['symfony_logs', 'symfony_container'],
            ],
        ]);

        $adapter = $this->toolReportingAdapter([
            new ToolCall('symfony_logs', startedAtMs: 1500.0),
            new ToolCall('symfony_logs', startedAtMs: 1900.0),
            new ToolCall('symfony_profiler', startedAtMs: 2200.0, errored: true),
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: $adapter,
            runId: 'run-test',
            mateEnabled: true,
            keepWorkspace: true,
        ));

        $this->assertSame(RunStatus::Passed, $outcome->status);
        $this->assertTrue($outcome->mateMetrics->enabled);
        $this->assertSame(3, $outcome->mateMetrics->toolCallCount);
        $this->assertSame(['symfony_logs', 'symfony_profiler'], $outcome->mateMetrics->toolNames);
        $this->assertSame(1500.0, $outcome->mateMetrics->firstToolCallMs);
        $this->assertSame(1, $outcome->mateMetrics->toolErrors);
        $this->assertSame(['symfony_container'], $outcome->mateMetrics->missingExpectedTools);
        $this->assertFileExists($outcome->workspace->path.'/mcp.json');
    }

    public function testOutcomeIncludesPopulatedMetricsBag(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'do nothing'],
            'expected' => ['pass_commands' => ['php -r "exit(0);"']],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: new NullAdapter(),
            runId: 'run-test',
        ));

        $values = $outcome->metrics->toArray();
        foreach (MetricsBag::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $values);
        }
        $this->assertSame(1, $outcome->metrics->get('commands_passed'));
        $this->assertSame(0, $outcome->metrics->get('commands_failed'));
        $this->assertSame(0, $outcome->metrics->get('files_changed_count'));
        $this->assertGreaterThan(0.0, $outcome->metrics->get('duration_ms'));
    }

    public function testRunOutcomeCarriesEvaluationsAndScore(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'do nothing'],
            'expected' => ['pass_commands' => ['php -r "exit(0);"']],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: new NullAdapter(),
            runId: 'run-test',
        ));

        $this->assertNotEmpty($outcome->evaluations);
        $this->assertContains('functional', array_map(static fn ($e) => $e->name, $outcome->evaluations));
        $this->assertGreaterThanOrEqual(0.0, $outcome->score->finalScore);
        $this->assertLessThanOrEqual(5.0, $outcome->score->finalScore);
        $this->assertArrayHasKey('functional', $outcome->score->perCategory);
    }

    public function testKeepWorkspacePreservesWorkspaceDirectory(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'whatever'],
            'expected' => ['pass_commands' => []],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: new NullAdapter(),
            runId: 'run-test',
            keepWorkspace: true,
        ));

        $this->assertDirectoryExists($outcome->workspace->path);
    }

    private function createRunner(?MateProvisionerInterface $provisioner = null): ScenarioRunner
    {
        $executor = new CommandExecutor();

        return new ScenarioRunner(
            projectRoot: $this->tmp,
            workspaceFactory: new WorkspaceFactory($this->tmp.'/var'),
            fixtureCopier: new FixtureCopier(),
            commandExecutor: $executor,
            diffCollector: new GitDiffCollector($executor),
            mateConfigurationFactory: new MateConfigurationFactory($provisioner ?? new FakeMateProvisioner()),
            mateMetricsCollector: new MateMetricsCollector(),
            metricsAggregator: new MetricsAggregator(),
            evaluationPipeline: new EvaluationPipeline(),
            scoreCalculator: ScoreCalculator::withDefaults(),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function scenario(array $overrides): Scenario
    {
        return Scenario::fromArray(array_replace_recursive([
            'id' => 'runner.test',
            'suite' => 'unit',
            'difficulty' => 'easy',
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'do nothing'],
            'expected' => ['pass_commands' => []],
        ], $overrides), '/virtual/runner.test.yaml');
    }

    private function throwingAdapter(string $message): AssistantAdapterInterface
    {
        return new class($message) implements AssistantAdapterInterface {
            public function __construct(private readonly string $message)
            {
            }

            public function name(): string
            {
                return 'throwing';
            }

            public function run(AssistantRunInput $input): AssistantRunResult
            {
                throw new \RuntimeException($this->message);
            }
        };
    }

    /**
     * @param list<ToolCall> $toolCalls
     */
    private function toolReportingAdapter(array $toolCalls): AssistantAdapterInterface
    {
        return new class($toolCalls) implements AssistantAdapterInterface {
            /**
             * @param list<ToolCall> $toolCalls
             */
            public function __construct(private readonly array $toolCalls)
            {
            }

            public function name(): string
            {
                return 'tool-reporting';
            }

            public function run(AssistantRunInput $input): AssistantRunResult
            {
                return AssistantRunResult::success(
                    stdout: 'tool-reporting',
                    durationMs: 1.0,
                    toolCalls: $this->toolCalls,
                );
            }
        };
    }
}
