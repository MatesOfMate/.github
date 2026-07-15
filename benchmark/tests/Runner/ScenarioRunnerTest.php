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
use MatesOfMate\Benchmark\Runner\CommandExecutor;
use MatesOfMate\Benchmark\Runner\FixtureCopier;
use MatesOfMate\Benchmark\Runner\GitDiffCollector;
use MatesOfMate\Benchmark\Runner\RunRequest;
use MatesOfMate\Benchmark\Runner\RunStatus;
use MatesOfMate\Benchmark\Runner\ScenarioRunner;
use MatesOfMate\Benchmark\Runner\WorkspaceFactory;
use MatesOfMate\Benchmark\Scenario\Scenario;
use MatesOfMate\Benchmark\Scoring\ScoreCalculator;
use MatesOfMate\Benchmark\Tests\Fixtures\Mate\FakeMateProvisioner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScenarioRunnerTest extends TestCase
{
    private const string FIX_CHECK_COMMAND = 'php -r "exit(file_exists(\'fixed.txt\') ? 0 : 1);"';

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

    public function testAdapterFixMakesOutcomePassed(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'Find the bug.'],
            'expected' => ['pass_commands' => [self::FIX_CHECK_COMMAND]],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: $this->fixingAdapter(['fixed.txt' => "done\n"]),
            runId: 'run-test',
            attempt: 1,
        ));

        $this->assertSame(RunStatus::Passed, $outcome->status);
        $this->assertNotNull($outcome->assistantResult);
        $this->assertTrue($outcome->assistantResult->successful);
        $this->assertNotNull($outcome->diff);
        $this->assertSame(['fixed.txt'], $outcome->diff->changedFiles);
        $this->assertCount(1, $outcome->verificationResults);
        $this->assertTrue($outcome->verificationResults[0]->successful());
        // The red-check ran and proved the scenario was actually broken.
        $this->assertCount(1, $outcome->baselineRedResults);
        $this->assertFalse($outcome->baselineRedResults[0]->successful());
    }

    public function testNullAdapterFailsWhenFixIsRequired(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'Find the bug.'],
            'expected' => ['pass_commands' => [self::FIX_CHECK_COMMAND]],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: new NullAdapter(),
            runId: 'run-test',
        ));

        $this->assertSame(RunStatus::Failed, $outcome->status);
        $this->assertNotNull($outcome->diff);
        $this->assertSame([], $outcome->diff->changedFiles, 'NullAdapter must not change the workspace.');
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

    public function testRedCheckInvalidatesScenarioAndSkipsAdapter(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'Nothing is broken here.'],
            // Succeeds against the untouched fixture: the scenario proves nothing.
            'expected' => ['pass_commands' => ['php -r "exit(0);"']],
        ]);

        $adapter = $this->spyAdapter();

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: $adapter,
            runId: 'run-test',
        ));

        $this->assertSame(RunStatus::InvalidScenario, $outcome->status);
        $this->assertSame(0, $adapter->invocations, 'Adapter must not be invoked for an invalid scenario.');
        $this->assertNull($outcome->assistantResult);
        $this->assertNull($outcome->diff);
        $this->assertSame([], $outcome->verificationResults);
        $this->assertCount(1, $outcome->baselineRedResults);
        $this->assertTrue($outcome->baselineRedResults[0]->successful());
        $this->assertNotNull($outcome->errorMessage);
        $this->assertStringContainsString('before the assistant', $outcome->errorMessage);
    }

    public function testPromptContainsBenchmarkRulesAppendix(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'Fix the widget.'],
            'expected' => [
                'pass_commands' => ['php tests/test.php'],
                'forbidden_files_changed' => ['tests/test.php'],
            ],
        ]);

        $adapter = $this->spyAdapter();

        $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: $adapter,
            runId: 'run-test',
        ));

        $this->assertSame(1, $adapter->invocations);
        $prompt = $adapter->lastInput?->prompt;
        $this->assertNotNull($prompt);
        $this->assertStringStartsWith('Fix the widget.', $prompt);
        $this->assertStringContainsString('Benchmark rules:', $prompt);
        $this->assertStringContainsString('`php tests/test.php`', $prompt);
        $this->assertStringContainsString('You must not modify: `tests/test.php`', $prompt);
        $this->assertStringContainsString('Work only inside the current directory.', $prompt);
    }

    public function testForbiddenFilesAreRestoredBeforeVerification(): void
    {
        $originalTest = '<?php exit(\'fixed\' === require __DIR__.\'/../app.php\' ? 0 : 1);'."\n";

        $cheatFixture = $this->tmp.'/cheat-fixture';
        $this->filesystem->mkdir($cheatFixture.'/tests');
        file_put_contents($cheatFixture.'/app.php', '<?php return \'broken\';'."\n");
        file_put_contents($cheatFixture.'/tests/test.php', $originalTest);

        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $cheatFixture],
            'task' => ['prompt' => 'Make the test pass.'],
            'expected' => [
                'pass_commands' => ['php tests/test.php'],
                'forbidden_files_changed' => ['tests/test.php'],
            ],
        ]);

        // A cheating assistant: instead of fixing app.php it rewrites the
        // protected test to always pass.
        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: $this->fixingAdapter(['tests/test.php' => '<?php exit(0);'."\n"]),
            runId: 'run-test',
            keepWorkspace: true,
        ));

        // Verification ran against the restored baseline test, so the cheat
        // did not produce a pass.
        $this->assertSame(RunStatus::Failed, $outcome->status);
        $this->assertCount(1, $outcome->verificationResults);
        $this->assertFalse($outcome->verificationResults[0]->successful());

        // The tampering stays visible in the diff and triggers the
        // forbidden_changes gate, zeroing the final score.
        $this->assertNotNull($outcome->diff);
        $this->assertContains('tests/test.php', $outcome->diff->changedFiles);
        $this->assertArrayHasKey('forbidden_changes', $outcome->score->gatePenalties);
        $this->assertSame(0.0, $outcome->score->gatePenalties['forbidden_changes']);
        $this->assertSame(0.0, $outcome->score->finalScore);

        // The workspace copy of the protected file is back at baseline content.
        $this->assertStringEqualsFile($outcome->workspace->path.'/tests/test.php', $originalTest);
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
        $this->assertSame('adapter exploded', $outcome->errorMessage);
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

    public function testMateEnabledWritesConfigAndAggregatesMcpToolCalls(): void
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
            // Built-in (non-MCP) tools never count as Mate usage.
            new ToolCall('Bash', arguments: ['command' => 'ls'], startedAtMs: 900.0),
            new ToolCall('symfony_logs', startedAtMs: 1500.0, mcp: true),
            new ToolCall('symfony_logs', startedAtMs: 1900.0, mcp: true),
            new ToolCall('symfony_profiler', errored: true, startedAtMs: 2200.0, mcp: true),
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
            'task' => ['prompt' => 'do the fix'],
            'expected' => ['pass_commands' => [self::FIX_CHECK_COMMAND]],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: $this->fixingAdapter(['fixed.txt' => "done\n"]),
            runId: 'run-test',
        ));

        $values = $outcome->metrics->toArray();
        foreach (MetricsBag::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $values);
        }
        $this->assertSame(1, $outcome->metrics->get('commands_passed'));
        $this->assertSame(0, $outcome->metrics->get('commands_failed'));
        $this->assertSame(1, $outcome->metrics->get('files_changed_count'));
        $this->assertGreaterThan(0.0, $outcome->metrics->get('duration_ms'));
    }

    public function testRunOutcomeCarriesEvaluationsAndScore(): void
    {
        $runner = $this->createRunner();
        $scenario = $this->scenario([
            'fixture' => ['path' => $this->fixtureDir],
            'task' => ['prompt' => 'do the fix'],
            'expected' => ['pass_commands' => [self::FIX_CHECK_COMMAND]],
        ]);

        $outcome = $runner->run(new RunRequest(
            scenario: $scenario,
            adapter: $this->fixingAdapter(['fixed.txt' => "done\n"]),
            runId: 'run-test',
        ));

        $this->assertNotEmpty($outcome->evaluations);
        $this->assertContains('functional', array_map(static fn (\MatesOfMate\Benchmark\Evaluator\EvaluationResult $e): string => $e->name, $outcome->evaluations));
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
        return new readonly class($message) implements AssistantAdapterInterface {
            public function __construct(private string $message)
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
     * Adapter that writes the given relative file => content map into the workspace.
     *
     * @param array<string, string> $files
     */
    private function fixingAdapter(array $files): AssistantAdapterInterface
    {
        return new readonly class($files) implements AssistantAdapterInterface {
            /**
             * @param array<string, string> $files
             */
            public function __construct(private array $files)
            {
            }

            public function name(): string
            {
                return 'fixing';
            }

            public function run(AssistantRunInput $input): AssistantRunResult
            {
                foreach ($this->files as $relativePath => $content) {
                    $path = $input->workspacePath.'/'.$relativePath;
                    if (!is_dir(\dirname($path))) {
                        mkdir(\dirname($path), 0777, true);
                    }
                    file_put_contents($path, $content);
                }

                return AssistantRunResult::success(
                    stdout: 'fixing adapter applied changes',
                    durationMs: 1.0,
                );
            }
        };
    }

    /**
     * @return AssistantAdapterInterface&object{invocations: int, lastInput: ?AssistantRunInput}
     */
    private function spyAdapter(): AssistantAdapterInterface
    {
        return new class implements AssistantAdapterInterface {
            public int $invocations = 0;

            public ?AssistantRunInput $lastInput = null;

            public function name(): string
            {
                return 'spy';
            }

            public function run(AssistantRunInput $input): AssistantRunResult
            {
                ++$this->invocations;
                $this->lastInput = $input;

                return AssistantRunResult::success(stdout: 'spy', durationMs: 1.0);
            }
        };
    }

    /**
     * @param list<ToolCall> $toolCalls
     */
    private function toolReportingAdapter(array $toolCalls): AssistantAdapterInterface
    {
        return new readonly class($toolCalls) implements AssistantAdapterInterface {
            /**
             * @param list<ToolCall> $toolCalls
             */
            public function __construct(private array $toolCalls)
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
