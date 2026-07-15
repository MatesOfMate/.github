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
 * Permanently-degenerate metrics (`time_to_first_tool_call_ms`,
 * `time_to_first_code_change_ms`, `redundant_tool_call_count`) are deliberately
 * omitted from the Markdown; they remain available in `results.json`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MarkdownReportWriter implements ReportWriterInterface
{
    public const FILENAME = 'summary.md';

    private const array SCORE_CATEGORIES = [
        'functional',
        'root_cause',
        'mate_tool_usage',
        'minimality',
        'verification',
        'efficiency',
    ];

    private const string NOT_AVAILABLE = '–';

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
        $invalid = 0;
        $scoredSum = 0.0;
        $scoredCount = 0;
        $passedSum = 0.0;

        foreach ($context->outcomes as $outcome) {
            match ($outcome->status) {
                RunStatus::Passed => ++$passed,
                RunStatus::Failed => ++$failed,
                RunStatus::AdapterError, RunStatus::SetupError => ++$errors,
                RunStatus::InvalidScenario => ++$invalid,
            };

            // Only genuinely scored attempts count towards the average —
            // adapter/setup errors and invalid scenarios say nothing about
            // the assistant's ability.
            if (RunStatus::Passed === $outcome->status || RunStatus::Failed === $outcome->status) {
                $scoredSum += $outcome->score->finalScore;
                ++$scoredCount;
            }
            if (RunStatus::Passed === $outcome->status) {
                $passedSum += $outcome->score->finalScore;
            }
        }

        $passRate = 0 === $total ? 0.0 : 100.0 * $passed / $total;
        $scoredAverage = 0 === $scoredCount ? self::NOT_AVAILABLE : \sprintf('%.2f', $scoredSum / $scoredCount);
        $passedAverage = 0 === $passed ? self::NOT_AVAILABLE : \sprintf('%.2f', $passedSum / $passed);

        return \sprintf(
            "## Summary\n\n| Total | Passed | Failed | Errors | Invalid scenarios | Pass rate | Avg score (scored runs) | Avg score (passed runs) |\n|---:|---:|---:|---:|---:|---:|---:|---:|\n| %d | %d | %d | %d | %d | %.1f%% | %s | %s |\n\n_Avg score (scored runs) covers passed + failed attempts only; adapter/setup errors and invalid scenarios are excluded._",
            $total,
            $passed,
            $failed,
            $errors,
            $invalid,
            $passRate,
            $scoredAverage,
            $passedAverage,
        );
    }

    private function adapterSection(ReportContext $context): string
    {
        return "## Adapter comparison\n\nThis run only exercised `".$context->adapter.'`. Compare runs across adapters by aggregating multiple `results.json` files.';
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

            $categoryCells = [];
            foreach (self::SCORE_CATEGORIES as $category) {
                $categoryCells[] = $this->categoryCell($outcome, $category);
            }

            $rows[] = \sprintf(
                '| `%s` | %d | %s | %.2f | %s | %s | %.0fms | %d | %s | %s |',
                $outcome->scenario->id,
                $outcome->workspace->attempt,
                $this->statusLabel($outcome->status),
                $outcome->score->finalScore,
                implode(' | ', $categoryCells),
                $this->costCell($outcome),
                $outcome->totalDurationMs,
                $files,
                $this->errorCell($outcome),
                $this->artefactLinks($outcome, $key),
            );
        }

        return "## Scenario results\n\n"
            ."| Scenario | Attempt | Status | Score | Functional | Root cause | Mate tools | Minimality | Verification | Efficiency | Cost | Duration | Files | Error | Artefacts |\n"
            ."|---|---:|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---|---|\n"
            .implode("\n", $rows);
    }

    private function categoryCell(RunOutcome $outcome, string $category): string
    {
        if (\in_array($category, $outcome->score->notApplicable, true)) {
            return self::NOT_AVAILABLE;
        }

        $value = $outcome->score->perCategory[$category] ?? null;

        return null === $value ? self::NOT_AVAILABLE : \sprintf('%.1f', $value);
    }

    private function costCell(RunOutcome $outcome): string
    {
        $cost = $outcome->metrics->get('cost_usd');
        if (!\is_float($cost) && !\is_int($cost)) {
            $cost = $outcome->assistantResult?->tokenUsage?->costUsd;
        }

        return null === $cost ? self::NOT_AVAILABLE : \sprintf('$%.4f', $cost);
    }

    private function errorCell(RunOutcome $outcome): string
    {
        if (RunStatus::Passed === $outcome->status || null === $outcome->errorMessage) {
            return self::NOT_AVAILABLE;
        }

        return $this->inline($outcome->errorMessage, 80);
    }

    private function artefactLinks(RunOutcome $outcome, string $key): string
    {
        $links = [];
        // The ArtifactsWriter only writes a .diff file for non-empty diffs;
        // never link artefacts that do not exist.
        if ($outcome->diff instanceof \MatesOfMate\Benchmark\Runner\DiffResult && '' !== $outcome->diff->diff) {
            $links[] = \sprintf('[diff](diffs/%s.diff)', $key);
        }
        $links[] = \sprintf('[log](logs/%s.log)', $key);

        return implode(' · ', $links);
    }

    private function statusLabel(RunStatus $status): string
    {
        return match ($status) {
            RunStatus::Passed => 'passed',
            RunStatus::Failed => 'failed',
            RunStatus::AdapterError => 'adapter_error',
            RunStatus::SetupError => 'setup_error',
            RunStatus::InvalidScenario => 'invalid_scenario',
        };
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
        $fresh = 0;
        $cached = 0;
        $cost = 0.0;
        $hasCost = false;
        $hasData = false;

        foreach ($context->outcomes as $outcome) {
            $usage = $outcome->assistantResult?->tokenUsage;
            if (null === $usage) {
                continue;
            }
            $hasData = true;
            $input += $usage->inputTokens;
            $output += $usage->outputTokens;
            $fresh += $usage->freshTokens();
            $cached += $usage->cachedTokens;
            if (null !== $usage->costUsd) {
                $hasCost = true;
                $cost += $usage->costUsd;
            }
        }

        if (!$hasData) {
            return "## Token usage\n\n_no token data reported_";
        }

        $rows = [
            \sprintf('| fresh input_tokens | %d |', $input),
            \sprintf('| output_tokens | %d |', $output),
            \sprintf('| fresh_tokens (input + output) | %d |', $fresh),
            \sprintf('| cached_tokens (cache reads, billed at a fraction) | %d |', $cached),
        ];
        if ($hasCost) {
            $rows[] = \sprintf('| cost_usd | $%.4f |', $cost);
        }

        return "## Token usage\n\nFresh tokens are the actual consumption; cache reads are how CLI agents are supposed to work and are reported separately, not counted as consumption.\n\n| Metric | Total |\n|---|---:|\n".implode("\n", $rows);
    }

    private function slowestSection(ReportContext $context): string
    {
        if ([] === $context->outcomes) {
            return '';
        }

        $sorted = $context->outcomes;
        usort($sorted, static fn (RunOutcome $a, RunOutcome $b): int => $b->totalDurationMs <=> $a->totalDurationMs);
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
            static fn (RunOutcome $o): bool => RunStatus::Passed !== $o->status,
        );

        if ([] === $failures) {
            return "## Failed scenarios\n\n_none — every scenario passed_";
        }

        $rows = [];
        foreach ($failures as $outcome) {
            $rows[] = \sprintf(
                '| `%s` | attempt %d | %s | %s | %s |',
                $outcome->scenario->id,
                $outcome->workspace->attempt,
                $this->statusLabel($outcome->status),
                null !== $outcome->errorMessage ? $this->inline($outcome->errorMessage, 160) : self::NOT_AVAILABLE,
                $this->failedCommandCell($outcome),
            );
        }

        return "## Failed scenarios\n\n| Scenario | Attempt | Status | Error | Failed pass command |\n|---|---:|---|---|---|\n".implode("\n", $rows);
    }

    private function failedCommandCell(RunOutcome $outcome): string
    {
        foreach ($outcome->verificationResults as $result) {
            if ($result->successful()) {
                continue;
            }

            $cell = \sprintf('`%s` (exit %d)', $this->inline($result->command, 60), $result->exitCode);
            $output = '' !== trim($result->stderr) ? $result->stderr : $result->stdout;
            if ('' !== trim($output)) {
                $cell .= ': '.$this->inline($output, 120);
            }

            return $cell;
        }

        return self::NOT_AVAILABLE;
    }

    /**
     * Collapses whitespace, truncates, and escapes pipes so arbitrary command
     * output cannot break the Markdown table layout.
     */
    private function inline(string $text, int $maxLength): string
    {
        $collapsed = trim((string) preg_replace('/\s+/', ' ', $text));
        if ('' === $collapsed) {
            return self::NOT_AVAILABLE;
        }

        if (mb_strlen($collapsed) > $maxLength) {
            $collapsed = mb_substr($collapsed, 0, $maxLength).'…';
        }

        return str_replace('|', '\\|', $collapsed);
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
