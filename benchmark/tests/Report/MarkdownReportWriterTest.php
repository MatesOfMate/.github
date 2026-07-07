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
use MatesOfMate\Benchmark\Mate\MateMetrics;
use MatesOfMate\Benchmark\Report\MarkdownReportWriter;
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
class MarkdownReportWriterTest extends TestCase
{
    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-md-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testRendersEverySection(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: 'ok',
                durationMs: 1.0,
                tokenUsage: new TokenUsage(50, 70),
            ),
            diff: new DiffResult(diff: 'some-diff', stat: '', changedFiles: ['x.php'], additions: 2, deletions: 1),
            mateMetrics: new MateMetrics(true, 1, ['x'], 1500.0, 0),
            verificationResults: [RunOutcomeBuilder::passingCommand()],
        )->withEvaluations([], new Score(4.0, 4.0, [], []));

        $failed = RunOutcomeBuilder::build(status: RunStatus::Failed);

        $context = new ReportContext(
            runId: 'run-md',
            reportDirectory: $this->tmp,
            adapter: 'null',
            mateEnabled: true,
            model: 'sonnet',
            repeat: 2,
            outcomes: [$outcome, $failed],
            startedAt: new \DateTimeImmutable('2026-04-27 20:00:00'),
            finishedAt: new \DateTimeImmutable('2026-04-27 20:00:05'),
        );

        (new MarkdownReportWriter())->write($context);

        $markdown = file_get_contents($this->tmp.'/summary.md');
        $this->assertNotFalse($markdown);

        foreach ([
            '# Benchmark run `run-md`',
            '## Summary',
            '## Adapter comparison',
            '## Mate enabled vs disabled',
            '## Scenario results',
            '## Tool usage',
            '## Token usage',
            '## Slowest runs',
            '## Failed scenarios',
            '## Most changed files',
        ] as $heading) {
            $this->assertStringContainsString($heading, $markdown);
        }

        $this->assertStringContainsString('eval.test', $markdown);
        $this->assertStringContainsString('attempt', $markdown);
        $this->assertStringContainsString('diffs/', $markdown);
        $this->assertStringContainsString('logs/', $markdown);
    }

    public function testFallbackTextWhenNoToolUsageOrTokens(): void
    {
        $context = new ReportContext(
            runId: 'empty',
            reportDirectory: $this->tmp,
            adapter: 'null',
            mateEnabled: false,
            model: null,
            repeat: 1,
            outcomes: [RunOutcomeBuilder::build()],
            startedAt: new \DateTimeImmutable('now'),
            finishedAt: new \DateTimeImmutable('now'),
        );

        (new MarkdownReportWriter())->write($context);
        $markdown = file_get_contents($this->tmp.'/summary.md');
        $this->assertNotFalse($markdown);
        $this->assertStringContainsString('_no tool calls observed_', $markdown);
        $this->assertStringContainsString('_no token data reported_', $markdown);
    }
}
