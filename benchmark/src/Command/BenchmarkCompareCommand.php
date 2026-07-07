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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Diffs two benchmark results.json files side by side.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand(
    name: 'benchmark:compare',
    description: 'Compare two benchmark results.json files (score, tokens, duration, Mate usage).',
)]
class BenchmarkCompareCommand extends Command
{
    public function __construct(
        private readonly ?string $reportsDirectory = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('left', InputArgument::OPTIONAL, 'Path to the first results.json file.')
            ->addArgument('right', InputArgument::OPTIONAL, 'Path to the second results.json file.')
            ->addOption('latest', 'l', InputOption::VALUE_NONE, 'Compare the two most recent results.json files in the reports directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $latest = (bool) $input->getOption('latest');
        $leftArg = $input->getArgument('left');
        $rightArg = $input->getArgument('right');

        try {
            if ($latest) {
                if (null !== $leftArg || null !== $rightArg) {
                    throw new \RuntimeException('--latest cannot be combined with explicit "left"/"right" arguments.');
                }

                [$leftPath, $rightPath] = $this->findLatestPair();
            } else {
                if (null === $leftArg || null === $rightArg) {
                    throw new \RuntimeException('Provide either --latest or both "left" and "right" arguments.');
                }

                $leftPath = (string) $leftArg;
                $rightPath = (string) $rightArg;
            }

            $left = $this->loadReport($leftPath);
            $right = $this->loadReport($rightPath);
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $io->title('Benchmark comparison');
        $this->renderHeader($io, $left, $right, $leftPath, $rightPath);
        $this->renderScenarioTable($io, $left, $right);
        $this->renderSummary($io, $left, $right);

        return Command::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string} The older report path first, newer second
     */
    private function findLatestPair(): array
    {
        if (null === $this->reportsDirectory) {
            throw new \RuntimeException('Reports directory is not configured; pass paths explicitly.');
        }

        if (!is_dir($this->reportsDirectory)) {
            throw new \RuntimeException(\sprintf('Reports directory "%s" does not exist.', $this->reportsDirectory));
        }

        // Run IDs are timestamp-prefixed (YYYYMMDD-HHMMSS-XXXX), so a reverse
        // lexicographic sort gives newest-first without hitting the filesystem
        // for mtimes.
        $entries = (array) scandir($this->reportsDirectory, \SCANDIR_SORT_DESCENDING);
        $candidates = [];
        foreach ($entries as $entry) {
            if (!\is_string($entry) || \in_array($entry, ['.', '..'], true)) {
                continue;
            }
            $resultsPath = $this->reportsDirectory.'/'.$entry.'/results.json';
            if (is_file($resultsPath)) {
                $candidates[] = $resultsPath;
                if (2 === \count($candidates)) {
                    break;
                }
            }
        }

        if (\count($candidates) < 2) {
            throw new \RuntimeException(\sprintf(
                'Need at least two reports under "%s" to compare; found %d.',
                $this->reportsDirectory,
                \count($candidates),
            ));
        }

        // Newest goes on the right so the diff reads "previous → latest".
        return [$candidates[1], $candidates[0]];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadReport(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('Report file "%s" does not exist.', $path));
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Unable to read report file "%s".', $path));
        }

        try {
            $payload = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(\sprintf('Invalid JSON in "%s": %s', $path, $exception->getMessage()));
        }

        if (!\is_array($payload) || !isset($payload['scenarios']) || !\is_array($payload['scenarios'])) {
            throw new \RuntimeException(\sprintf('Report "%s" is missing a "scenarios" array.', $path));
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function renderHeader(SymfonyStyle $io, array $left, array $right, string $leftPath, string $rightPath): void
    {
        $io->definitionList(
            ['left' => $leftPath],
            ['right' => $rightPath],
            ['left adapter' => (string) ($left['adapter'] ?? '?')],
            ['right adapter' => (string) ($right['adapter'] ?? '?')],
            ['left mate' => ($left['mate_enabled'] ?? false) ? 'enabled' : 'disabled'],
            ['right mate' => ($right['mate_enabled'] ?? false) ? 'enabled' : 'disabled'],
        );
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function renderScenarioTable(SymfonyStyle $io, array $left, array $right): void
    {
        $byKey = [];
        foreach ($this->extractScenarios($left) as $scenario) {
            $byKey[$scenario['__key']] = ['left' => $scenario, 'right' => null];
        }
        foreach ($this->extractScenarios($right) as $scenario) {
            $byKey[$scenario['__key']]['right'] = $scenario;
            $byKey[$scenario['__key']]['left'] ??= null;
        }

        ksort($byKey);

        $rows = [];
        foreach ($byKey as $key => $pair) {
            $l = $pair['left'];
            $r = $pair['right'];
            $rows[] = [
                $key,
                $this->formatScore($l, $r),
                $this->formatTokens($l, $r),
                $this->formatDuration($l, $r),
                $this->formatMate($l, $r),
            ];
        }

        $io->section('Scenario diff');
        $io->table(['Scenario#attempt', 'Score (left → right)', 'Tokens', 'Duration', 'Mate calls'], $rows);
    }

    /**
     * @param array<string, mixed> $report
     *
     * @return list<array<string, mixed>>
     */
    private function extractScenarios(array $report): array
    {
        $scenarios = [];
        foreach ($report['scenarios'] as $scenario) {
            if (!\is_array($scenario)) {
                continue;
            }
            $key = (string) ($scenario['id'] ?? '?').'#'.(int) ($scenario['attempt'] ?? 1);
            $scenario['__key'] = $key;
            $scenarios[] = $scenario;
        }

        return $scenarios;
    }

    /**
     * @param array<string, mixed>|null $left
     * @param array<string, mixed>|null $right
     */
    private function formatScore(?array $left, ?array $right): string
    {
        $l = null !== $left ? (float) ($left['score']['final'] ?? 0.0) : null;
        $r = null !== $right ? (float) ($right['score']['final'] ?? 0.0) : null;

        return $this->formatPair($l, $r, '%.2f', 'score');
    }

    /**
     * @param array<string, mixed>|null $left
     * @param array<string, mixed>|null $right
     */
    private function formatTokens(?array $left, ?array $right): string
    {
        $l = $this->intMetric($left, 'total_tokens');
        $r = $this->intMetric($right, 'total_tokens');

        return $this->formatPair($l, $r, '%d', 'tokens');
    }

    /**
     * @param array<string, mixed>|null $left
     * @param array<string, mixed>|null $right
     */
    private function formatDuration(?array $left, ?array $right): string
    {
        $l = null !== $left ? (float) ($left['duration_ms'] ?? 0.0) : null;
        $r = null !== $right ? (float) ($right['duration_ms'] ?? 0.0) : null;

        return $this->formatPair($l, $r, '%.0fms', 'duration');
    }

    /**
     * @param array<string, mixed>|null $left
     * @param array<string, mixed>|null $right
     */
    private function formatMate(?array $left, ?array $right): string
    {
        $l = null !== $left ? (int) ($left['mate']['tool_call_count'] ?? 0) : null;
        $r = null !== $right ? (int) ($right['mate']['tool_call_count'] ?? 0) : null;

        return $this->formatPair($l, $r, '%d', 'calls');
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function intMetric(?array $entry, string $key): ?int
    {
        if (null === $entry) {
            return null;
        }
        $value = $entry['metrics'][$key] ?? null;

        return \is_int($value) ? $value : null;
    }

    private function formatPair(int|float|null $left, int|float|null $right, string $format, string $label): string
    {
        if (null === $left && null === $right) {
            return '—';
        }

        if (null === $left) {
            return \sprintf('— → '.$format, $right);
        }

        if (null === $right) {
            return \sprintf($format.' → —', $left);
        }

        $delta = $right - $left;
        $sign = $delta > 0 ? '+' : ($delta < 0 ? '' : '±');

        return \sprintf(
            $format.' → '.$format.'  ('.$sign.$format.' '.$label.')',
            $left,
            $right,
            $delta,
        );
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function renderSummary(SymfonyStyle $io, array $left, array $right): void
    {
        $leftAvg = (float) ($left['summary']['average_score'] ?? 0.0);
        $rightAvg = (float) ($right['summary']['average_score'] ?? 0.0);
        $delta = $rightAvg - $leftAvg;

        $io->section('Summary');
        $io->writeln(\sprintf(
            ' average score:   %.2f → %.2f  (%s%.2f)',
            $leftAvg,
            $rightAvg,
            $delta > 0 ? '+' : '',
            $delta,
        ));
        $io->writeln(\sprintf(
            ' passed:          %d → %d',
            (int) ($left['summary']['passed'] ?? 0),
            (int) ($right['summary']['passed'] ?? 0),
        ));
        $io->writeln(\sprintf(
            ' failed/errors:   %d → %d',
            (int) ($left['summary']['failed'] ?? 0) + (int) ($left['summary']['errors'] ?? 0),
            (int) ($right['summary']['failed'] ?? 0) + (int) ($right['summary']['errors'] ?? 0),
        ));
    }
}
