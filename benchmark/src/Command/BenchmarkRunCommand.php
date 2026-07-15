<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Command;

use MatesOfMate\Benchmark\Adapter\AdapterRegistry;
use MatesOfMate\Benchmark\Adapter\Exception\UnsupportedAdapterException;
use MatesOfMate\Benchmark\Report\ReportContext;
use MatesOfMate\Benchmark\Report\ReportPipeline;
use MatesOfMate\Benchmark\Runner\RunOutcome;
use MatesOfMate\Benchmark\Runner\RunRequest;
use MatesOfMate\Benchmark\Runner\RunStatus;
use MatesOfMate\Benchmark\Runner\ScenarioRunner;
use MatesOfMate\Benchmark\Runner\WorkspaceFactory;
use MatesOfMate\Benchmark\Scenario\Scenario;
use MatesOfMate\Benchmark\Scenario\ScenarioRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists, filters and executes benchmark scenarios against the selected adapter.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand(
    name: 'benchmark:run',
    description: 'Run benchmark scenarios against an AI adapter.',
)]
class BenchmarkRunCommand extends Command
{
    public const OUTPUT_JSON = 'json';
    public const OUTPUT_MARKDOWN = 'markdown';

    public const MATE_ENABLED = 'enabled';
    public const MATE_DISABLED = 'disabled';

    public function __construct(
        private readonly ScenarioRepository $repository,
        private readonly AdapterRegistry $adapters,
        private readonly ScenarioRunner $runner,
        private readonly WorkspaceFactory $workspaceFactory,
        private readonly ReportPipeline $reportPipeline,
        private readonly string $reportsDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('scenario', null, InputOption::VALUE_REQUIRED, 'Run a single scenario by ID.')
            ->addOption('suite', null, InputOption::VALUE_REQUIRED, 'Run all scenarios from a given suite.')
            ->addOption('adapter', null, InputOption::VALUE_REQUIRED, 'AI adapter to use (defaults to "null").', 'null')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Model identifier passed to the adapter.')
            ->addOption('mate', null, InputOption::VALUE_REQUIRED, 'Mate integration: enabled or disabled.', self::MATE_ENABLED)
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Report format: json or markdown.', self::OUTPUT_MARKDOWN)
            ->addOption('repeat', null, InputOption::VALUE_REQUIRED, 'Number of times each scenario is executed.', '1')
            ->addOption('keep-workspace', null, InputOption::VALUE_NONE, 'Keep the isolated workspace directory after execution.')
            ->addOption('list', null, InputOption::VALUE_NONE, 'List the resolved scenarios without executing them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $adapter = $this->adapters->get((string) $input->getOption('adapter'));
        } catch (UnsupportedAdapterException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $mate = $this->normalizeChoice(
            (string) $input->getOption('mate'),
            [self::MATE_ENABLED, self::MATE_DISABLED],
            'mate',
            $io,
        );
        if (null === $mate) {
            return Command::INVALID;
        }

        $outputFormat = $this->normalizeChoice(
            (string) $input->getOption('output'),
            [self::OUTPUT_JSON, self::OUTPUT_MARKDOWN],
            'output',
            $io,
        );
        if (null === $outputFormat) {
            return Command::INVALID;
        }

        $repeat = (int) $input->getOption('repeat');
        if ($repeat < 1) {
            $io->error('Option --repeat must be a positive integer.');

            return Command::INVALID;
        }

        $scenarios = $this->resolveScenarios(
            scenarioId: $input->getOption('scenario'),
            suite: $input->getOption('suite'),
            io: $io,
        );

        if (null === $scenarios) {
            return Command::INVALID;
        }

        $scenarios = $this->filterForMateState($scenarios, self::MATE_ENABLED === $mate, $io);

        if ([] === $scenarios) {
            $io->warning('No scenarios matched the given filters.');

            return Command::SUCCESS;
        }

        $runId = $this->workspaceFactory->generateRunId();
        $keep = (bool) $input->getOption('keep-workspace');
        $model = $input->getOption('model');

        $io->title('Benchmark scenarios');
        $io->definitionList(
            ['run-id' => $runId],
            ['adapter' => $adapter->name()],
            ['mate' => $mate],
            ['output' => $outputFormat],
            ['repeat' => (string) $repeat],
            ['model' => null !== $model ? (string) $model : 'default'],
            ['keep-workspace' => $keep ? 'yes' : 'no'],
        );

        if ($input->getOption('list')) {
            $this->renderScenarioList($io, $scenarios);

            return Command::SUCCESS;
        }

        $startedAt = new \DateTimeImmutable('now');
        $outcomes = [];
        foreach ($scenarios as $scenario) {
            for ($attempt = 1; $attempt <= $repeat; ++$attempt) {
                $request = new RunRequest(
                    scenario: $scenario,
                    adapter: $adapter,
                    runId: $runId,
                    attempt: $attempt,
                    model: null !== $model ? (string) $model : null,
                    mateEnabled: self::MATE_ENABLED === $mate,
                    keepWorkspace: $keep,
                );

                $outcome = $this->runner->run($request);
                $outcomes[] = $outcome;
                $this->renderOutcomeLine($io, $outcome);
            }
        }

        $this->renderSummary($io, $outcomes);

        $reportDir = rtrim($this->reportsDirectory, '/').'/'.$runId;
        $this->reportPipeline->emit(new ReportContext(
            runId: $runId,
            reportDirectory: $reportDir,
            adapter: $adapter->name(),
            mateEnabled: self::MATE_ENABLED === $mate,
            model: null !== $model ? (string) $model : null,
            repeat: $repeat,
            outcomes: $outcomes,
            startedAt: $startedAt,
            finishedAt: new \DateTimeImmutable('now'),
        ));
        $io->writeln(\sprintf('<info>Report written to:</info> %s', $reportDir));

        return $this->hasFailure($outcomes) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param list<string> $allowed
     */
    private function normalizeChoice(string $value, array $allowed, string $optionName, SymfonyStyle $io): ?string
    {
        if (\in_array($value, $allowed, true)) {
            return $value;
        }

        $io->error(\sprintf(
            'Invalid value "%s" for --%s. Allowed: %s.',
            $value,
            $optionName,
            implode(', ', $allowed),
        ));

        return null;
    }

    /**
     * @return list<Scenario>|null
     */
    private function resolveScenarios(mixed $scenarioId, mixed $suite, SymfonyStyle $io): ?array
    {
        if (null !== $scenarioId && null !== $suite) {
            $io->error('Options --scenario and --suite cannot be used together.');

            return null;
        }

        if (null !== $scenarioId) {
            $scenario = $this->repository->find((string) $scenarioId);

            if (!$scenario instanceof Scenario) {
                $io->error(\sprintf('Scenario "%s" was not found.', (string) $scenarioId));

                return null;
            }

            return [$scenario];
        }

        if (null !== $suite && 'all' !== $suite) {
            return $this->repository->bySuite((string) $suite);
        }

        return $this->repository->all();
    }

    /**
     * Scenarios tagged `mate-only` measure Mate tool usage specifically and
     * are meaningless (often unsolvable) without a provisioned Mate setup, so
     * they are skipped when Mate is disabled.
     *
     * @param list<Scenario> $scenarios
     *
     * @return list<Scenario>
     */
    private function filterForMateState(array $scenarios, bool $mateEnabled, SymfonyStyle $io): array
    {
        if ($mateEnabled) {
            return $scenarios;
        }

        $kept = [];
        foreach ($scenarios as $scenario) {
            if (\in_array('mate-only', $scenario->tags, true)) {
                $io->writeln(\sprintf('<comment>Skipping %s (tagged mate-only, but --mate=disabled).</comment>', $scenario->id));
                continue;
            }

            $kept[] = $scenario;
        }

        return $kept;
    }

    /**
     * @param list<Scenario> $scenarios
     */
    private function renderScenarioList(SymfonyStyle $io, array $scenarios): void
    {
        $rows = [];
        foreach ($scenarios as $scenario) {
            $rows[] = [$scenario->id, $scenario->suite, $scenario->difficulty ?? '-'];
        }

        $io->table(['ID', 'Suite', 'Difficulty'], $rows);
        $io->writeln(\sprintf('<info>%d scenario(s) listed.</info>', \count($scenarios)));
    }

    private function renderOutcomeLine(SymfonyStyle $io, RunOutcome $outcome): void
    {
        $diff = $outcome->diff;
        $files = $diff instanceof \MatesOfMate\Benchmark\Runner\DiffResult ? \count($diff->changedFiles) : 0;
        $mate = $outcome->mateMetrics;
        $mateLabel = $mate->enabled
            ? \sprintf('mate=on tools=%d', $mate->toolCallCount)
            : 'mate=off';

        $io->writeln(\sprintf(
            '  <comment>%-40s</comment> attempt %d  status=<info>%s</info>  score=<info>%4.2f</info>  duration=%6.0fms  files=%d  %s',
            $outcome->scenario->id,
            $outcome->workspace->attempt,
            $outcome->status->value,
            $outcome->score->finalScore,
            $outcome->totalDurationMs,
            $files,
            $mateLabel,
        ));
    }

    /**
     * @param list<RunOutcome> $outcomes
     */
    private function renderSummary(SymfonyStyle $io, array $outcomes): void
    {
        $total = \count($outcomes);
        $passed = 0;
        $failed = 0;
        $errored = 0;
        $invalid = 0;

        foreach ($outcomes as $outcome) {
            match ($outcome->status) {
                RunStatus::Passed => ++$passed,
                RunStatus::Failed => ++$failed,
                RunStatus::AdapterError, RunStatus::SetupError => ++$errored,
                RunStatus::InvalidScenario => ++$invalid,
            };
        }

        $io->section('Summary');
        $io->writeln(\sprintf(' <info>passed</info>=%d  <comment>failed</comment>=%d  <error>errors</error>=%d  invalid=%d  total=%d', $passed, $failed, $errored, $invalid, $total));
    }

    /**
     * @param list<RunOutcome> $outcomes
     */
    private function hasFailure(array $outcomes): bool
    {
        foreach ($outcomes as $outcome) {
            if (RunStatus::Passed !== $outcome->status) {
                return true;
            }
        }

        return false;
    }
}
