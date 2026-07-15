<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Evaluator;

use MatesOfMate\Benchmark\Evaluator\DiffMinimalityEvaluator;
use MatesOfMate\Benchmark\Evaluator\EvaluationInput;
use MatesOfMate\Benchmark\Runner\DiffResult;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class DiffMinimalityEvaluatorTest extends TestCase
{
    public function testEmptyDiffScoresZero(): void
    {
        $outcome = RunOutcomeBuilder::build(
            diff: new DiffResult(diff: '', stat: '', changedFiles: [], additions: 0, deletions: 0),
        );

        $result = (new DiffMinimalityEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testMatchingExpectedFilesScoresFive(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['expected_files_changed' => ['config/services.yaml']]],
            diff: new DiffResult(
                diff: '',
                stat: '',
                changedFiles: ['config/services.yaml'],
                additions: 4,
                deletions: 1,
            ),
        );

        $result = (new DiffMinimalityEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
    }

    public function testOverreachReducesScore(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['expected_files_changed' => ['config/services.yaml']]],
            diff: new DiffResult(
                diff: '',
                stat: '',
                changedFiles: ['a.php', 'b.php', 'c.php', 'd.php', 'e.php', 'f.php'],
                additions: 200,
                deletions: 50,
            ),
        );

        $result = (new DiffMinimalityEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertLessThanOrEqual(1.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testNoDiffYieldsZero(): void
    {
        $outcome = RunOutcomeBuilder::build();

        $result = (new DiffMinimalityEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testWithoutExpectationScoreFollowsFileCountTiers(): void
    {
        $evaluator = new DiffMinimalityEvaluator();

        $tiers = [
            [['a.php'], 5.0, true],
            [['a.php', 'b.php'], 4.0, true],
            [['a.php', 'b.php', 'c.php'], 3.0, false],
            [['a.php', 'b.php', 'c.php', 'd.php', 'e.php'], 2.0, false],
            [['a.php', 'b.php', 'c.php', 'd.php', 'e.php', 'f.php', 'g.php', 'h.php', 'i.php'], 1.0, false],
        ];

        foreach ($tiers as [$files, $expectedScore, $expectedPassed]) {
            $outcome = RunOutcomeBuilder::build(
                diff: new DiffResult(diff: '', stat: '', changedFiles: $files, additions: 1, deletions: 0),
            );

            $result = $evaluator->evaluate(new EvaluationInput($outcome->scenario, $outcome));

            $this->assertSame($expectedScore, $result->score, \sprintf('%d file(s) changed', \count($files)));
            $this->assertSame($expectedPassed, $result->passed, \sprintf('%d file(s) changed', \count($files)));
        }
    }

    public function testExtraFileHalvesJaccardScoreAndFailsPass(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['expected_files_changed' => ['a.php']]],
            diff: new DiffResult(diff: '', stat: '', changedFiles: ['a.php', 'b.php'], additions: 2, deletions: 0),
        );

        $result = (new DiffMinimalityEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        // Jaccard: |{a}| / |{a, b}| = 1/2 -> 2.5; only the exact set passes.
        $this->assertSame(2.5, $result->score);
        $this->assertFalse($result->passed);
        $this->assertSame(['b.php'], $result->evidence['extra_files']);
    }

    public function testMissingExpectedFileFailsEvenWhenSubsetMatches(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['expected_files_changed' => ['a.php', 'b.php']]],
            diff: new DiffResult(diff: '', stat: '', changedFiles: ['a.php'], additions: 1, deletions: 0),
        );

        $result = (new DiffMinimalityEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(2.5, $result->score);
        $this->assertFalse($result->passed);
        $this->assertSame(['b.php'], $result->evidence['missing_expected']);
    }
}
