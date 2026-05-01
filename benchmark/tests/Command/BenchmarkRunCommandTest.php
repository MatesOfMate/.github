<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Command;

use MatesOfMate\Benchmark\Adapter\AdapterRegistry;
use MatesOfMate\Benchmark\Adapter\NullAdapter;
use MatesOfMate\Benchmark\Command\BenchmarkRunCommand;
use MatesOfMate\Benchmark\Evaluator\EvaluationPipeline;
use MatesOfMate\Benchmark\Mate\MateConfigurationFactory;
use MatesOfMate\Benchmark\Mate\MateMetricsCollector;
use MatesOfMate\Benchmark\Metrics\MetricsAggregator;
use MatesOfMate\Benchmark\Report\ReportPipeline;
use MatesOfMate\Benchmark\Scoring\ScoreCalculator;
use MatesOfMate\Benchmark\Runner\CommandExecutor;
use MatesOfMate\Benchmark\Runner\FixtureCopier;
use MatesOfMate\Benchmark\Runner\GitDiffCollector;
use MatesOfMate\Benchmark\Runner\ScenarioRunner;
use MatesOfMate\Benchmark\Runner\WorkspaceFactory;
use MatesOfMate\Benchmark\Scenario\ScenarioLoader;
use MatesOfMate\Benchmark\Scenario\ScenarioRepository;
use MatesOfMate\Benchmark\Scenario\ScenarioValidator;
use MatesOfMate\Benchmark\Tests\Fixtures\Mate\FakeMateProvisioner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class BenchmarkRunCommandTest extends TestCase
{
    private const SCHEMA_PATH = __DIR__.'/../../schema/scenario.schema.json';
    private const SCENARIOS_DIR = __DIR__.'/../Fixtures/scenarios';

    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-cmd-'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testCommandIsRegistered(): void
    {
        $application = new Application();
        $application->addCommand($this->createCommand());

        $this->assertTrue($application->has('benchmark:run'));
    }

    public function testHasAllRequiredOptions(): void
    {
        $command = $this->createCommand();
        $definition = $command->getDefinition();

        $expected = ['scenario', 'suite', 'adapter', 'model', 'mate', 'output', 'repeat', 'keep-workspace', 'list'];
        foreach ($expected as $option) {
            $this->assertTrue($definition->hasOption($option), \sprintf('Option --%s is missing.', $option));
        }
    }

    public function testListFlagListsScenariosWithoutExecuting(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--list' => true]);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('bug.example', $output);
        $this->assertStringContainsString('code.minimal', $output);
        $this->assertStringContainsString('2 scenario(s) listed', $output);
    }

    public function testFiltersBySuiteWhenListing(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--list' => true, '--suite' => 'code-generation']);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('code.minimal', $output);
        $this->assertStringNotContainsString('bug.example', $output);
    }

    public function testFiltersByScenarioIdWhenListing(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--list' => true, '--scenario' => 'bug.example']);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('bug.example', $tester->getDisplay());
    }

    public function testSuiteAllReturnsAllScenarios(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--list' => true, '--suite' => 'all']);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('bug.example', $output);
        $this->assertStringContainsString('code.minimal', $output);
        $this->assertStringContainsString('2 scenario(s) listed', $output);
    }

    public function testUnknownScenarioReturnsInvalid(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--list' => true, '--scenario' => 'does.not.exist']);

        $this->assertSame(Command::INVALID, $exit);
    }

    public function testUnknownAdapterReturnsInvalid(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--adapter' => 'codex']);

        $this->assertSame(Command::INVALID, $exit);
    }

    public function testScenarioAndSuiteAreMutuallyExclusive(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute([
            '--list' => true,
            '--scenario' => 'bug.example',
            '--suite' => 'bug-finding',
        ]);

        $this->assertSame(Command::INVALID, $exit);
    }

    public function testRunsScenarioEndToEndWithNullAdapter(): void
    {
        $command = $this->createCommandForScenarios(__DIR__.'/../Fixtures/runner-scenarios');
        $tester = new CommandTester($command);

        $exit = $tester->execute(['--adapter' => 'null']);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('runner.smoke', $output);
        $this->assertStringContainsString('status=passed', $output);
        $this->assertStringContainsString('Summary', $output);
        $this->assertStringContainsString('passed=1', $output);
    }

    public function testMateFlagPropagatesToOutcomeLine(): void
    {
        $command = $this->createCommandForScenarios(__DIR__.'/../Fixtures/runner-scenarios');
        $tester = new CommandTester($command);

        $tester->execute(['--adapter' => 'null', '--mate' => 'enabled']);
        $this->assertStringContainsString('mate=on', $tester->getDisplay());

        $tester->execute(['--adapter' => 'null', '--mate' => 'disabled']);
        $this->assertStringContainsString('mate=off', $tester->getDisplay());
    }

    public function testInvalidMateValueReturnsInvalid(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--mate' => 'maybe']);

        $this->assertSame(Command::INVALID, $exit);
    }

    public function testReportArtefactsAreWrittenAfterRun(): void
    {
        $command = $this->createCommandForScenarios(__DIR__.'/../Fixtures/runner-scenarios');
        $tester = new CommandTester($command);

        $exit = $tester->execute(['--adapter' => 'null']);

        $this->assertSame(Command::SUCCESS, $exit);
        $reportsRoot = $this->tmp.'/reports';
        $this->assertDirectoryExists($reportsRoot);

        $runDirs = glob($reportsRoot.'/*');
        $this->assertNotFalse($runDirs);
        $this->assertCount(1, $runDirs);
        $reportDir = $runDirs[0];

        $this->assertFileExists($reportDir.'/results.json');
        $this->assertFileExists($reportDir.'/summary.md');
        $this->assertDirectoryExists($reportDir.'/logs');
    }

    public function testRepeatRunsEachScenarioMultipleTimes(): void
    {
        $command = $this->createCommandForScenarios(__DIR__.'/../Fixtures/runner-scenarios');
        $tester = new CommandTester($command);

        $exit = $tester->execute(['--adapter' => 'null', '--repeat' => '3']);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('attempt 1', $output);
        $this->assertStringContainsString('attempt 2', $output);
        $this->assertStringContainsString('attempt 3', $output);
        $this->assertStringContainsString('passed=3', $output);
    }

    private function createCommand(): BenchmarkRunCommand
    {
        return $this->createCommandForScenarios(self::SCENARIOS_DIR);
    }

    private function createCommandForScenarios(string $scenariosDir): BenchmarkRunCommand
    {
        $repository = new ScenarioRepository(
            $scenariosDir,
            new ScenarioLoader(),
            new ScenarioValidator(self::SCHEMA_PATH),
        );

        $workspaceFactory = new WorkspaceFactory($this->tmp.'/var');
        $executor = new CommandExecutor();
        $runner = new ScenarioRunner(
            // Resolve fixture paths from the benchmark package root so scenarios
            // referencing tests/Fixtures/... continue to work in tests.
            projectRoot: \dirname(__DIR__, 2),
            workspaceFactory: $workspaceFactory,
            fixtureCopier: new FixtureCopier(),
            commandExecutor: $executor,
            diffCollector: new GitDiffCollector($executor),
            mateConfigurationFactory: new MateConfigurationFactory(new FakeMateProvisioner()),
            mateMetricsCollector: new MateMetricsCollector(),
            metricsAggregator: new MetricsAggregator(),
            evaluationPipeline: new EvaluationPipeline(),
            scoreCalculator: ScoreCalculator::withDefaults(),
        );

        $adapters = new AdapterRegistry([new NullAdapter()]);

        return new BenchmarkRunCommand(
            repository: $repository,
            adapters: $adapters,
            runner: $runner,
            workspaceFactory: $workspaceFactory,
            reportPipeline: new ReportPipeline(),
            reportsDirectory: $this->tmp.'/reports',
        );
    }
}
