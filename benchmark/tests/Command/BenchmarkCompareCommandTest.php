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

use MatesOfMate\Benchmark\Command\BenchmarkCompareCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class BenchmarkCompareCommandTest extends TestCase
{
    private string $tmp;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmp = sys_get_temp_dir().'/bench-cmp-'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $this->filesystem->remove($this->tmp);
        }
    }

    public function testRendersSideBySideDiff(): void
    {
        $left = $this->writeReport('left.json', [
            'adapter' => 'codex',
            'mate_enabled' => true,
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0, 'errors' => 0, 'average_score' => 4.0],
            'scenarios' => [
                $this->scenario('bug.failing-phpunit', 4.0, 1500, 5000.0, 3),
            ],
        ]);
        $right = $this->writeReport('right.json', [
            'adapter' => 'claude',
            'mate_enabled' => true,
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0, 'errors' => 0, 'average_score' => 4.5],
            'scenarios' => [
                $this->scenario('bug.failing-phpunit', 4.5, 1300, 4000.0, 5),
            ],
        ]);

        $tester = new CommandTester(new BenchmarkCompareCommand());
        $exit = $tester->execute(['left' => $left, 'right' => $right]);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Benchmark comparison', $output);
        $this->assertStringContainsString('bug.failing-phpunit', $output);
        $this->assertStringContainsString('4.00 → 4.50', $output);
        $this->assertStringContainsString('+0.50 score', $output);
        $this->assertStringContainsString('5000ms → 4000ms', $output);
        $this->assertStringContainsString('1500 → 1300', $output);
        $this->assertStringContainsString('+0.50', $output);
        $this->assertStringContainsString('pass rate:', $output);
        $this->assertStringContainsString('100.0% → 100.0%', $output);
    }

    public function testRendersPerCategoryScoreDeltas(): void
    {
        $left = $this->writeReport('left.json', [
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0, 'errors' => 0, 'average_score' => 3.0],
            'scenarios' => [
                $this->scenario('bug.failing-phpunit', 3.0, 1000, 5000.0, 1, perCategory: [
                    'functional' => 3.0,
                    'root_cause' => 2.0,
                    'efficiency' => null,
                ]),
            ],
        ]);
        $right = $this->writeReport('right.json', [
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0, 'errors' => 0, 'average_score' => 4.5],
            'scenarios' => [
                $this->scenario('bug.failing-phpunit', 4.5, 900, 4000.0, 2, perCategory: [
                    'functional' => 5.0,
                    'root_cause' => 2.0,
                    'efficiency' => 4.0,
                ]),
            ],
        ]);

        $tester = new CommandTester(new BenchmarkCompareCommand());
        $exit = $tester->execute(['left' => $left, 'right' => $right]);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('functional +2.00', $output);
        $this->assertStringContainsString('root_cause +0.00', $output);
        // Null on one side (not applicable) renders as an explicit one-sided pair.
        $this->assertStringContainsString('efficiency —→4.00', $output);
    }

    public function testRendersCostDeltaWhenBothRunsReportCost(): void
    {
        $left = $this->writeReport('left.json', [
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0, 'errors' => 0, 'average_score' => 4.0],
            'scenarios' => [$this->scenario('bug.failing-phpunit', 4.0, 1000, 5000.0, 1, cost: 0.10)],
        ]);
        $right = $this->writeReport('right.json', [
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0, 'errors' => 0, 'average_score' => 4.5],
            'scenarios' => [$this->scenario('bug.failing-phpunit', 4.5, 900, 4000.0, 2, cost: 0.25)],
        ]);

        $tester = new CommandTester(new BenchmarkCompareCommand());
        $tester->execute(['left' => $left, 'right' => $right]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('cost:', $output);
        $this->assertStringContainsString('$0.1000 → $0.2500', $output);
        $this->assertStringContainsString('+0.1500 USD', $output);
    }

    public function testCostDeltaIsOmittedWhenOnlyOneRunReportsCost(): void
    {
        $left = $this->writeReport('left.json', [
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0, 'errors' => 0, 'average_score' => 4.0],
            'scenarios' => [$this->scenario('bug.failing-phpunit', 4.0, 1000, 5000.0, 1)],
        ]);
        $right = $this->writeReport('right.json', [
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0, 'errors' => 0, 'average_score' => 4.5],
            'scenarios' => [$this->scenario('bug.failing-phpunit', 4.5, 900, 4000.0, 2, cost: 0.25)],
        ]);

        $tester = new CommandTester(new BenchmarkCompareCommand());
        $tester->execute(['left' => $left, 'right' => $right]);

        $this->assertStringNotContainsString('cost:', $tester->getDisplay());
    }

    public function testWarnsAndComparesIntersectionWhenScenarioIdSetsDiffer(): void
    {
        $left = $this->writeReport('left.json', [
            'summary' => ['total' => 2, 'passed' => 2, 'failed' => 0, 'errors' => 0, 'average_score' => 4.5],
            'scenarios' => [
                $this->scenario('shared.scenario', 4.0, 100, 50.0, 0),
                $this->scenario('only.left', 5.0, 100, 50.0, 0),
            ],
        ]);
        $right = $this->writeReport('right.json', [
            'summary' => ['total' => 2, 'passed' => 1, 'failed' => 1, 'errors' => 0, 'average_score' => 2.5],
            'scenarios' => [
                $this->scenario('shared.scenario', 4.5, 90, 40.0, 0),
                $this->scenario('only.right', 1.0, 200, 80.0, 0),
            ],
        ]);

        $tester = new CommandTester(new BenchmarkCompareCommand());
        $exit = $tester->execute(['left' => $left, 'right' => $right]);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('different scenario id sets', $output);
        $this->assertStringContainsString('Only in left: only.left', $output);
        $this->assertStringContainsString('Only in right: only.right', $output);
        // The intersection is still compared.
        $this->assertStringContainsString('shared.scenario', $output);
        $this->assertStringContainsString('4.00 → 4.50', $output);
    }

    public function testHandlesEntirelyDisjointScenarioSets(): void
    {
        $left = $this->writeReport('left.json', [
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0, 'errors' => 0, 'average_score' => 5.0],
            'scenarios' => [$this->scenario('only.left', 5.0, 100, 50.0, 0)],
        ]);
        $right = $this->writeReport('right.json', [
            'summary' => ['total' => 1, 'passed' => 0, 'failed' => 1, 'errors' => 0, 'average_score' => 1.0],
            'scenarios' => [$this->scenario('only.right', 1.0, 200, 80.0, 0)],
        ]);

        $tester = new CommandTester(new BenchmarkCompareCommand());
        $exit = $tester->execute(['left' => $left, 'right' => $right]);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Only in left: only.left', $output);
        $this->assertStringContainsString('Only in right: only.right', $output);
        $this->assertStringContainsString('No overlapping scenarios to compare', $output);
    }

    public function testMissingFileReturnsInvalid(): void
    {
        $tester = new CommandTester(new BenchmarkCompareCommand());
        $exit = $tester->execute([
            'left' => $this->tmp.'/missing.json',
            'right' => $this->tmp.'/missing.json',
        ]);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('does not exist', $tester->getDisplay());
    }

    public function testInvalidJsonReturnsInvalid(): void
    {
        $broken = $this->tmp.'/broken.json';
        file_put_contents($broken, 'not-json');

        $tester = new CommandTester(new BenchmarkCompareCommand());
        $exit = $tester->execute(['left' => $broken, 'right' => $broken]);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('Invalid JSON', $tester->getDisplay());
    }

    public function testLatestPicksTwoMostRecentReportsWithNewerOnTheRight(): void
    {
        $reports = $this->tmp.'/reports';
        $this->filesystem->mkdir($reports);

        // Lexicographic order matches chronological order for these IDs, so
        // 20260102 must end up on the right (newer) and 20260101 on the left.
        $this->writeRunReport(
            $reports.'/20260101-100000-aaa/results.json',
            'old', 1.5,
            $this->scenario('bug.failing-phpunit', 1.5, 9000, 8000.0, 0),
        );
        $this->writeRunReport(
            $reports.'/20260102-100000-bbb/results.json',
            'new', 4.0,
            $this->scenario('bug.failing-phpunit', 4.0, 4000, 5000.0, 2),
        );

        $tester = new CommandTester(new BenchmarkCompareCommand($reports));
        $exit = $tester->execute(['--latest' => true]);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        // The diff direction proves order: 1.50 (older) → 4.00 (newer).
        $this->assertStringContainsString('1.50 → 4.00', $output);
        $this->assertStringContainsString('+2.50 score', $output);
        $this->assertStringContainsString('20260101-100000-aaa', $output);
        $this->assertStringContainsString('20260102-100000-bbb', $output);
    }

    public function testLatestSkipsRunDirectoriesWithoutResultsJson(): void
    {
        $reports = $this->tmp.'/reports';
        $this->filesystem->mkdir($reports);

        // A half-finished run with no results.json must not steal one of the
        // two slots — otherwise we'd compare against a missing file.
        $this->filesystem->mkdir($reports.'/20260103-100000-zzz');

        $this->writeRunReport(
            $reports.'/20260101-100000-aaa/results.json',
            'old', 1.0,
            $this->scenario('bug.failing-phpunit', 1.0, 100, 100.0, 0),
        );
        $this->writeRunReport(
            $reports.'/20260102-100000-bbb/results.json',
            'new', 5.0,
            $this->scenario('bug.failing-phpunit', 5.0, 200, 200.0, 0),
        );

        $tester = new CommandTester(new BenchmarkCompareCommand($reports));
        $exit = $tester->execute(['--latest' => true]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('1.00 → 5.00', $tester->getDisplay());
    }

    public function testLatestRequiresAtLeastTwoReports(): void
    {
        $reports = $this->tmp.'/reports';
        $this->filesystem->mkdir($reports);
        $this->writeRunReport(
            $reports.'/20260101-100000-aaa/results.json',
            'only', 1.0,
            $this->scenario('bug.x', 1.0, 0, 0.0, 0),
        );

        $tester = new CommandTester(new BenchmarkCompareCommand($reports));
        $exit = $tester->execute(['--latest' => true]);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('Need at least two reports', $tester->getDisplay());
    }

    public function testLatestRejectsExplicitArguments(): void
    {
        $reports = $this->tmp.'/reports';
        $this->filesystem->mkdir($reports);

        $tester = new CommandTester(new BenchmarkCompareCommand($reports));
        $exit = $tester->execute(['--latest' => true, 'left' => '/tmp/a.json']);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('cannot be combined', $tester->getDisplay());
    }

    public function testNoArgumentsAndNoLatestIsRejected(): void
    {
        $tester = new CommandTester(new BenchmarkCompareCommand($this->tmp));
        $exit = $tester->execute([]);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('Provide either --latest or both', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function writeRunReport(string $path, string $adapter, float $averageScore, array $scenario): void
    {
        $this->filesystem->mkdir(\dirname($path));
        file_put_contents($path, json_encode([
            'adapter' => $adapter,
            'mate_enabled' => true,
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0, 'errors' => 0, 'average_score' => $averageScore],
            'scenarios' => [$scenario],
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeReport(string $name, array $payload): string
    {
        $path = $this->tmp.'/'.$name;
        file_put_contents($path, json_encode($payload, \JSON_THROW_ON_ERROR));

        return $path;
    }

    /**
     * @param array<string, float|null> $perCategory
     *
     * @return array<string, mixed>
     */
    private function scenario(string $id, float $score, int $tokens, float $durationMs, int $mateCalls, array $perCategory = [], ?float $cost = null): array
    {
        $metrics = ['total_tokens' => $tokens];
        if (null !== $cost) {
            $metrics['cost_usd'] = $cost;
        }

        return [
            'id' => $id,
            'attempt' => 1,
            'status' => 'passed',
            'duration_ms' => $durationMs,
            'score' => ['final' => $score, 'per_category' => $perCategory],
            'metrics' => $metrics,
            'mate' => ['enabled' => true, 'tool_call_count' => $mateCalls],
        ];
    }
}
