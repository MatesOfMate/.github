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

use MatesOfMate\Benchmark\Scenario\Scenario;
use MatesOfMate\Benchmark\Scenario\ScenarioRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists, filters and (eventually) executes benchmark scenarios.
 *
 * AI execution is intentionally not implemented yet — at this stage the
 * command only loads scenarios from disk and prints them.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand(
    name: 'benchmark:run',
    description: 'Run benchmark scenarios against an AI adapter (currently lists scenarios).',
)]
class BenchmarkRunCommand extends Command
{
    public const ADAPTER_CODEX = 'codex';
    public const ADAPTER_CLAUDE = 'claude';
    public const ADAPTER_NULL = 'null';

    public const OUTPUT_JSON = 'json';
    public const OUTPUT_MARKDOWN = 'markdown';

    public const MATE_ENABLED = 'enabled';
    public const MATE_DISABLED = 'disabled';

    public function __construct(
        private readonly ScenarioRepository $repository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('scenario', null, InputOption::VALUE_REQUIRED, 'Run a single scenario by ID.')
            ->addOption('suite', null, InputOption::VALUE_REQUIRED, 'Run all scenarios from a given suite.')
            ->addOption('adapter', null, InputOption::VALUE_REQUIRED, 'AI adapter to use: codex, claude or null.', self::ADAPTER_NULL)
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Model identifier passed to the adapter.')
            ->addOption('mate', null, InputOption::VALUE_REQUIRED, 'Mate integration: enabled or disabled.', self::MATE_ENABLED)
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Report format: json or markdown.', self::OUTPUT_MARKDOWN)
            ->addOption('repeat', null, InputOption::VALUE_REQUIRED, 'Number of times each scenario is executed.', '1')
            ->addOption('keep-workspace', null, InputOption::VALUE_NONE, 'Keep the isolated workspace directory after execution.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $adapter = $this->normalizeChoice(
            (string) $input->getOption('adapter'),
            [self::ADAPTER_CODEX, self::ADAPTER_CLAUDE, self::ADAPTER_NULL],
            'adapter',
            $io,
        );
        if (null === $adapter) {
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

        if ([] === $scenarios) {
            $io->warning('No scenarios matched the given filters.');

            return Command::SUCCESS;
        }

        $io->title('Benchmark scenarios');
        $io->definitionList(
            ['adapter' => $adapter],
            ['mate' => $mate],
            ['output' => $outputFormat],
            ['repeat' => (string) $repeat],
            ['model' => (string) ($input->getOption('model') ?? 'default')],
            ['keep-workspace' => $input->getOption('keep-workspace') ? 'yes' : 'no'],
        );

        $rows = [];
        foreach ($scenarios as $scenario) {
            $rows[] = [
                $scenario->id,
                $scenario->suite,
                $scenario->difficulty ?? '-',
            ];
        }

        $io->table(['ID', 'Suite', 'Difficulty'], $rows);
        $io->writeln(\sprintf('<info>%d scenario(s) loaded.</info>', \count($scenarios)));
        $io->note('AI execution is not implemented yet. This command currently only validates and lists scenarios.');

        return Command::SUCCESS;
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

            if (null === $scenario) {
                $io->error(\sprintf('Scenario "%s" was not found.', (string) $scenarioId));

                return null;
            }

            return [$scenario];
        }

        if (null !== $suite) {
            return $this->repository->bySuite((string) $suite);
        }

        return $this->repository->all();
    }
}
