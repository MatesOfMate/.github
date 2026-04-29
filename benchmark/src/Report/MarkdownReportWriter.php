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

use MatesOfMate\Benchmark\Runner\RunOutcome;
use MatesOfMate\Benchmark\Runner\RunStatus;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Produces a human-readable `summary.md` covering all sections required by spec 09.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MarkdownReportWriter implements ReportWriterInterface
{
    public const FILENAME = 'summary.md';

    private readonly Filesystem $filesystem;

    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function write(ReportContext $context): void
    {
        $sections = [
            $this->headerSection($context),
            $this->summarySection($context),
            $this->adapterSection($context),
            $this->mateSection($context),
            $this->scenarioSection($context),
            $this->toolUsageSection($context),
            $this->tokenUsageSection($context),
            $this->slowestSection($context),
            $this->failedSection($context),
            $this->mostChangedFilesSection($context),
        ];

        $this->filesystem->mkdir($context->reportDirectory);
        $this->filesystem->dumpFile(
            rtrim($context->reportDirectory, '/').'/'.self::FILENAME,
            implode("\n\n", array_filter($sections)).
            "\n",
        );
    }

    private function headerSection(ReportContext $context): string
    {
        return \sprintf(
            "# Benchmark run `%s`\n\n- adapter: **%s**\n- model: %s\n- mate: **%s**\n- repeat: %d\n- started: %s\n- finished: %s\n- duration: %.0fs",
            $context->runId,
            $context->adapter,
            $context->model ?? 'default',
            $context->mateEnabled ? 'enabled' : 'disabled',
            $context->repeat,
            $context->startedAt->format(\DATE_ATOM),
            $context->finishedAt->format(\DATE_ATOM),
            $context->durationSeconds(),
        );
    }

    private function summarySection(ReportContext $context): string
    {
        $total = \count($context->outcomes);
        $passed = 0;
        $failed = 0;
        $errors = 0;
        $sumScore = 0.0;

        foreach ($context->outcomes as $outcome) {
            match ($outcome->status) {
                RunStatus::Passed => ++$passed,
                RunStatus::Failed => ++$failed,
                RunStatus::AdapterError, RunStatus::SetupError => ++$errors,
            };
            $sumScore += $outcome->score->finalScore;
        }

        $average = 0 === $total ? 0.0 : round($sumScore / $total, 2);

        return \sprintf(
            "## Summary\n\n| Total | Passed | Failed | Errors | Avg score |\n|---:|---:|---:|---:|---:|\n| %d | %d | %d | %d | %.2f |",
            $total,
            $passed,
            $failed,
            $errors,
            $average,
        );
    }

    private function adapterSection(ReportContext $context): string
    {
        return "## Adapter comparison\n\nThis run only exercised `".$context->adapter."`. Compare runs across adapters by aggregating multiple `results.json` files.";
    }

    private function mateSection(ReportContext $context): string
    {
        $label = $context->mateEnabled ? 'enabled' : 'disabled';
        $totalCalls = 0;
        $errors = 0;
        foreach ($context->outcomes as $outcome) {
            $totalCalls += $outcome->mateMetrics->toolCallCount;
            $errors += $outcome->mateMetrics->toolErrors;
        }

        return \sprintf(
            "## Mate enabled vs disabled\n\nMate is **%s** for this run. Total Mate tool calls: %d. Tool errors: %d. Run the same scenarios with the opposite `--mate` flag to enable a side-by-side comparison.",
            $label,
            $totalCalls,
            $errors,
        );
    }

    private function scenarioSection(ReportContext $context): string
    {
        if ([] === $context->outcomes) {
            return "## Scenario results\n\n_no scenarios executed_";
        }

        $rows = [];
        foreach ($context->outcomes as $outcome) {
            $key = $this->key($outcome);
            $diff = $outcome->diff;
            $files = null !== $diff ? \count($diff->changedFiles) : 0;
            $rows[] = \sprintf(
                '| `%s` | %d | %s | %.2f | %.0fms | %d | [diff](diffs/%s.diff) · [log](logs/%s.log) |',
                $outcome->scenario->id,
                $outcome->workspace->attempt,
                $outcome->status->value,
                $outcome->score->finalScore,
                $outcome->totalDurationMs,
                $files,
                $key,
                $key,
            );
        }

        return "## Scenario results\n\n| Scenario | Attempt | Status | Score | Duration | Files | Artefacts |\n|---|---:|---|---:|---:|---:|---|\n".implode("\n", $rows);
    }

    private function toolUsageSection(ReportContext $context): string
    {
        $counts = [];
        foreach ($context->outcomes as $outcome) {
            foreach ($outcome->mateMetrics->toolNames as $name) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
            if (null !== $outcome->assistantResult) {
                foreach ($outcome->assistantResult->toolCalls as $call) {
                    $counts[$call->name] = ($counts[$call->name] ?? 0) + 1;
                }
            }
        }

        if ([] === $counts) {
            return "## Tool usage\n\n_no tool calls observed_";
        }

        arsort($counts);
        $rows = [];
        foreach ($counts as $name => $count) {
            $rows[] = \sprintf('| `%s` | %d |', $name, $count);
        }

        return "## Tool usage\n\n| Tool | Calls |\n|---|---:|\n".implode("\n", $rows);
    }

    private function tokenUsageSection(ReportContext $context): string
    {
        $input = 0;
        $output = 0;
        $total = 0;
        $hasData = false;

        foreach ($context->outcomes as $outcome) {
            $usage = $outcome->assistantResult?->tokenUsage;
            if (null === $usage) {
                continue;
            }
            $hasData = true;
            $input += $usage->inputTokens;
            $output += $usage->outputTokens;
            $total += $usage->totalTokens();
        }

        if (!$hasData) {
            return "## Token usage\n\n_no token data reported_";
        }

        return \sprintf(
            "## Token usage\n\n| Metric | Total |\n|---|---:|\n| input_tokens | %d |\n| output_tokens | %d |\n| total_tokens | %d |",
            $input,
            $output,
            $total,
        );
    }

    private function slowestSection(ReportContext $context): string
    {
        if ([] === $context->outcomes) {
            return '';
        }

        $sorted = $context->outcomes;
        usort($sorted, static fn (RunOutcome $a, RunOutcome $b) => $b->totalDurationMs <=> $a->totalDurationMs);
        $top = \array_slice($sorted, 0, 5);

        $rows = [];
        foreach ($top as $outcome) {
            $rows[] = \sprintf('| `%s` | attempt %d | %.0fms |', $outcome->scenario->id, $outcome->workspace->attempt, $outcome->totalDurationMs);
        }

        return "## Slowest runs\n\n| Scenario | Attempt | Duration |\n|---|---:|---:|\n".implode("\n", $rows);
    }

    private function failedSection(ReportContext $context): string
    {
        $failures = array_filter(
            $context->outcomes,
            static fn (RunOutcome $o) => RunStatus::Passed !== $o->status,
        );

        if ([] === $failures) {
            return "## Failed scenarios\n\n_none — every scenario passed_";
        }

        $rows = [];
        foreach ($failures as $outcome) {
            $rows[] = \sprintf(
                '| `%s` | attempt %d | %s | %s |',
                $outcome->scenario->id,
                $outcome->workspace->attempt,
                $outcome->status->value,
                null !== $outcome->errorMessage ? trim((string) preg_replace('/\s+/', ' ', $outcome->errorMessage)) : '—',
            );
        }

        return "## Failed scenarios\n\n| Scenario | Attempt | Status | Error |\n|---|---:|---|---|\n".implode("\n", $rows);
    }

    private function mostChangedFilesSection(ReportContext $context): string
    {
        $counts = [];
        foreach ($context->outcomes as $outcome) {
            if (null === $outcome->diff) {
                continue;
            }
            foreach ($outcome->diff->changedFiles as $file) {
                $counts[$file] = ($counts[$file] ?? 0) + 1;
            }
        }

        if ([] === $counts) {
            return "## Most changed files\n\n_no diffs collected_";
        }

        arsort($counts);
        $top = \array_slice($counts, 0, 10, true);
        $rows = [];
        foreach ($top as $file => $count) {
            $rows[] = \sprintf('| `%s` | %d |', $file, $count);
        }

        return "## Most changed files\n\n| File | Changed in N runs |\n|---|---:|\n".implode("\n", $rows);
    }

    private function key(RunOutcome $outcome): string
    {
        $id = preg_replace('/[^a-zA-Z0-9._-]/', '_', $outcome->scenario->id) ?? $outcome->scenario->id;

        return \sprintf('%s-attempt-%d', $id, $outcome->workspace->attempt);
    }
}
