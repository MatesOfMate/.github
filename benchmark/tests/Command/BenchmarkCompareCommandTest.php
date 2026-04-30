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
    }

    public function testHandlesMissingScenariosOnEitherSide(): void
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
        $tester->execute(['left' => $left, 'right' => $right]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('only.left', $output);
        $this->assertStringContainsString('only.right', $output);
        $this->assertStringContainsString('— →', $output);
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
     * @return array<string, mixed>
     */
    private function scenario(string $id, float $score, int $tokens, float $durationMs, int $mateCalls): array
    {
        return [
            'id' => $id,
            'attempt' => 1,
            'status' => 'passed',
            'duration_ms' => $durationMs,
            'score' => ['final' => $score],
            'metrics' => ['total_tokens' => $tokens],
            'mate' => ['enabled' => true, 'tool_call_count' => $mateCalls],
        ];
    }
}
