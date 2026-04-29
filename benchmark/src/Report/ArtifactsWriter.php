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

use MatesOfMate\Benchmark\Runner\CommandResult;
use MatesOfMate\Benchmark\Runner\RunOutcome;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Persists per-scenario diffs, command logs, and raw assistant stdout/stderr.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ArtifactsWriter implements ReportWriterInterface
{
    private readonly Filesystem $filesystem;

    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function write(ReportContext $context): void
    {
        $base = rtrim($context->reportDirectory, '/');
        $this->filesystem->mkdir([$base.'/diffs', $base.'/logs', $base.'/raw']);

        foreach ($context->outcomes as $outcome) {
            $key = $this->key($outcome);

            if (null !== $outcome->diff && '' !== $outcome->diff->diff) {
                $this->filesystem->dumpFile($base.'/diffs/'.$key.'.diff', $outcome->diff->diff);
            }

            $this->filesystem->dumpFile($base.'/logs/'.$key.'.log', $this->buildLog($outcome));

            if (null !== $outcome->assistantResult) {
                $this->filesystem->dumpFile($base.'/raw/'.$key.'.stdout.txt', $outcome->assistantResult->stdout);
                $this->filesystem->dumpFile($base.'/raw/'.$key.'.stderr.txt', $outcome->assistantResult->stderr);
            }
        }
    }

    private function key(RunOutcome $outcome): string
    {
        $id = preg_replace('/[^a-zA-Z0-9._-]/', '_', $outcome->scenario->id) ?? $outcome->scenario->id;

        return \sprintf('%s-attempt-%d', $id, $outcome->workspace->attempt);
    }

    private function buildLog(RunOutcome $outcome): string
    {
        $sections = [];
        $sections[] = $this->section('SETUP', $outcome->setupResults);
        $sections[] = $this->section('BASELINE', $outcome->baselineResults);
        $sections[] = $this->section('VERIFY', $outcome->verificationResults);

        if (null !== $outcome->errorMessage) {
            $sections[] = "## ERROR\n".$outcome->errorMessage."\n";
        }

        return implode("\n", $sections);
    }

    /**
     * @param list<CommandResult> $results
     */
    private function section(string $heading, array $results): string
    {
        if ([] === $results) {
            return \sprintf("## %s\n(no commands)\n", $heading);
        }

        $body = '';
        foreach ($results as $i => $result) {
            $body .= \sprintf(
                "### [%s #%d] %s (exit=%d, duration=%.0fms%s)\n--- stdout ---\n%s\n--- stderr ---\n%s\n",
                $heading,
                $i + 1,
                $result->command,
                $result->exitCode,
                $result->durationMs,
                $result->timedOut ? ', TIMED OUT' : '',
                rtrim($result->stdout),
                rtrim($result->stderr),
            );
        }

        return \sprintf("## %s\n%s", $heading, $body);
    }
}
