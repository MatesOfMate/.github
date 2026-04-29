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
use MatesOfMate\Benchmark\Report\ArtifactsWriter;
use MatesOfMate\Benchmark\Report\ReportContext;
use MatesOfMate\Benchmark\Runner\DiffResult;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ArtifactsWriterTest extends TestCase
{
    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-art-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testWritesDiffsLogsAndRawFilesPerOutcome(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: 'assistant-stdout',
                durationMs: 1.0,
                stderr: 'assistant-stderr',
            ),
            diff: new DiffResult(diff: 'diff-body', stat: '', changedFiles: ['x.php'], additions: 1, deletions: 0),
            verificationResults: [RunOutcomeBuilder::passingCommand('php -r "exit(0);"')],
        );

        $context = new ReportContext(
            runId: 'art',
            reportDirectory: $this->tmp,
            adapter: 'null',
            mateEnabled: false,
            model: null,
            repeat: 1,
            outcomes: [$outcome],
            startedAt: new \DateTimeImmutable('now'),
            finishedAt: new \DateTimeImmutable('now'),
        );

        (new ArtifactsWriter())->write($context);

        $key = 'eval.test-attempt-1';
        $this->assertSame('diff-body', file_get_contents($this->tmp.'/diffs/'.$key.'.diff'));
        $this->assertSame('assistant-stdout', file_get_contents($this->tmp.'/raw/'.$key.'.stdout.txt'));
        $this->assertSame('assistant-stderr', file_get_contents($this->tmp.'/raw/'.$key.'.stderr.txt'));

        $log = file_get_contents($this->tmp.'/logs/'.$key.'.log');
        $this->assertNotFalse($log);
        $this->assertStringContainsString('## SETUP', $log);
        $this->assertStringContainsString('## VERIFY', $log);
        $this->assertStringContainsString('php -r "exit(0);"', $log);
    }

    public function testEmptyDiffSkipsDiffFile(): void
    {
        $outcome = RunOutcomeBuilder::build();

        $context = new ReportContext(
            runId: 'no-diff',
            reportDirectory: $this->tmp,
            adapter: 'null',
            mateEnabled: false,
            model: null,
            repeat: 1,
            outcomes: [$outcome],
            startedAt: new \DateTimeImmutable('now'),
            finishedAt: new \DateTimeImmutable('now'),
        );

        (new ArtifactsWriter())->write($context);

        $this->assertFileDoesNotExist($this->tmp.'/diffs/eval.test-attempt-1.diff');
    }
}
