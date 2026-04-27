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
        $outcome = RunOutcomeBuilder::build(diff: null);

        $result = (new DiffMinimalityEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }
}
