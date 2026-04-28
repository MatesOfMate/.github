<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Scoring;

use MatesOfMate\Benchmark\Evaluator\EvaluationResult;
use MatesOfMate\Benchmark\Scenario\Scenario;
use MatesOfMate\Benchmark\Scoring\ScoreCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScoreCalculatorTest extends TestCase
{
    public function testFullyPassingEvaluationsScoreFive(): void
    {
        $calculator = ScoreCalculator::withDefaults();
        $scenario = $this->scenario();

        $score = $calculator->calculate($scenario, [
            new EvaluationResult('functional', 5.0, true, ''),
            new EvaluationResult('root_cause', 5.0, true, ''),
            new EvaluationResult('mate_tool_usage', 5.0, true, ''),
            new EvaluationResult('minimality', 5.0, true, ''),
            new EvaluationResult('verification', 5.0, true, ''),
            new EvaluationResult('efficiency', 5.0, true, ''),
        ]);

        $this->assertEqualsWithDelta(5.0, $score->finalScore, 0.001);
        $this->assertEqualsWithDelta(5.0, $score->rawScore, 0.001);
        $this->assertSame([], $score->missingEvaluators);
        $this->assertSame([], $score->gatePenalties);
    }

    public function testWeightedSumMatchesPlanFormula(): void
    {
        $calculator = ScoreCalculator::withDefaults();

        $score = $calculator->calculate($this->scenario(), [
            new EvaluationResult('functional', 5.0, true, ''),
            new EvaluationResult('root_cause', 4.0, false, ''),
            new EvaluationResult('mate_tool_usage', 3.0, true, ''),
            new EvaluationResult('minimality', 2.0, false, ''),
            new EvaluationResult('verification', 4.0, true, ''),
            new EvaluationResult('efficiency', 5.0, true, ''),
        ]);

        $expected = 5 * 0.40 + 4 * 0.20 + 3 * 0.15 + 2 * 0.10 + 4 * 0.10 + 5 * 0.05;
        $this->assertEqualsWithDelta($expected, $score->finalScore, 0.01);
    }

    public function testMissingEvaluatorsAreReportedExplicitly(): void
    {
        $calculator = ScoreCalculator::withDefaults();

        $score = $calculator->calculate($this->scenario(), [
            new EvaluationResult('functional', 5.0, true, ''),
            new EvaluationResult('efficiency', 5.0, true, ''),
        ]);

        $this->assertContains('root_cause', $score->missingEvaluators);
        $this->assertContains('mate_tool_usage', $score->missingEvaluators);
        $this->assertNull($score->perCategory['root_cause']);
        $this->assertSame(5.0, $score->perCategory['functional']);
    }

    public function testForbiddenChangesGateHalvesFinalScore(): void
    {
        $calculator = ScoreCalculator::withDefaults();

        $score = $calculator->calculate($this->scenario(), [
            new EvaluationResult('functional', 5.0, true, ''),
            new EvaluationResult('root_cause', 5.0, true, ''),
            new EvaluationResult('mate_tool_usage', 5.0, true, ''),
            new EvaluationResult('minimality', 5.0, true, ''),
            new EvaluationResult('verification', 5.0, true, ''),
            new EvaluationResult('efficiency', 5.0, true, ''),
            new EvaluationResult('forbidden_changes', 0.0, false, ''),
        ]);

        $this->assertEqualsWithDelta(2.5, $score->finalScore, 0.001);
        $this->assertEqualsWithDelta(5.0, $score->rawScore, 0.001);
        $this->assertSame(['forbidden_changes' => 0.5], $score->gatePenalties);
    }

    public function testScenarioWeightsOverrideDefaults(): void
    {
        $calculator = ScoreCalculator::withDefaults();
        $scenario = Scenario::fromArray([
            'id' => 'weights',
            'suite' => 'unit',
            'fixture' => ['path' => '/tmp'],
            'task' => ['prompt' => 'x'],
            'expected' => [],
            'evaluation' => [
                'weights' => ['functional' => 1.0],
            ],
        ], '/virtual/weights.yaml');

        $score = $calculator->calculate($scenario, [
            new EvaluationResult('functional', 4.0, true, ''),
            new EvaluationResult('root_cause', 0.0, false, ''),
        ]);

        $this->assertEqualsWithDelta(4.0, $score->finalScore, 0.001);
        $this->assertSame(['functional'], array_keys($score->weights));
    }

    public function testFinalScoreIsClampedToValidRange(): void
    {
        $calculator = ScoreCalculator::withDefaults();

        $score = $calculator->calculate($this->scenario(), [
            new EvaluationResult('functional', 5.0, true, ''),
        ]);

        $this->assertGreaterThanOrEqual(0.0, $score->finalScore);
        $this->assertLessThanOrEqual(5.0, $score->finalScore);
    }

    private function scenario(): Scenario
    {
        return Scenario::fromArray([
            'id' => 'score.test',
            'suite' => 'unit',
            'fixture' => ['path' => '/tmp'],
            'task' => ['prompt' => 'x'],
            'expected' => [],
        ], '/virtual/score.test.yaml');
    }
}
