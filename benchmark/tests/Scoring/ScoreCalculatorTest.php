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
        $this->assertSame([], $score->notApplicable);
        $this->assertEqualsWithDelta(1.0, array_sum($score->effectiveWeights), 0.001);
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

    public function testMissingEvaluatorsAreExcludedAndWeightsRenormalised(): void
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

        // The two categories with data both scored 5.0, so renormalising
        // functional (0.40) + efficiency (0.05) must still yield a full score.
        $this->assertEqualsWithDelta(5.0, $score->finalScore, 0.001);
        $this->assertSame(['functional', 'efficiency'], array_keys($score->effectiveWeights));
        $this->assertEqualsWithDelta(1.0, array_sum($score->effectiveWeights), 0.001);
    }

    public function testNotApplicableCategoryIsExcludedAndWeightsRenormalised(): void
    {
        $calculator = ScoreCalculator::withDefaults();

        $score = $calculator->calculate($this->scenario(), [
            new EvaluationResult('functional', 4.0, true, ''),
            new EvaluationResult('root_cause', 2.0, false, ''),
            EvaluationResult::notApplicable('mate_tool_usage', 'Mate disabled.'),
            new EvaluationResult('minimality', 5.0, true, ''),
            new EvaluationResult('verification', 5.0, true, ''),
            new EvaluationResult('efficiency', 5.0, true, ''),
        ]);

        // Weight sum without mate_tool_usage (0.15) is 0.85, so:
        // (4*0.40 + 2*0.20 + 5*0.10 + 5*0.10 + 5*0.05) / 0.85 = 3.25 / 0.85.
        $this->assertEqualsWithDelta(3.25 / 0.85, $score->finalScore, 0.01);
        $this->assertSame(['mate_tool_usage'], $score->notApplicable);
        $this->assertNull($score->perCategory['mate_tool_usage']);
        $this->assertSame([], $score->missingEvaluators);
        $this->assertArrayNotHasKey('mate_tool_usage', $score->effectiveWeights);
        $this->assertEqualsWithDelta(0.40 / 0.85, $score->effectiveWeights['functional'], 0.001);
        $this->assertEqualsWithDelta(1.0, array_sum($score->effectiveWeights), 0.001);
        // The not-applicable evaluator's 0.0 score must not act as a gate.
        $this->assertSame([], $score->gatePenalties);
    }

    public function testForbiddenChangesGateZeroesFinalScore(): void
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

        // Touching protected files invalidates the attempt outright.
        $this->assertEqualsWithDelta(0.0, $score->finalScore, 0.001);
        $this->assertEqualsWithDelta(5.0, $score->rawScore, 0.001);
        $this->assertSame(['forbidden_changes' => 0.0], $score->gatePenalties);
    }

    public function testFunctionalGateQuartersFinalScoreWhenVerificationFailed(): void
    {
        $calculator = ScoreCalculator::withDefaults();

        $score = $calculator->calculate($this->scenario(), [
            new EvaluationResult('functional', 0.0, false, ''),
            new EvaluationResult('root_cause', 5.0, true, ''),
            new EvaluationResult('mate_tool_usage', 5.0, true, ''),
            new EvaluationResult('minimality', 5.0, true, ''),
            new EvaluationResult('verification', 5.0, true, ''),
            new EvaluationResult('efficiency', 5.0, true, ''),
        ]);

        // Raw: 0*0.40 + 5*0.60 = 3.0; a plausible-sounding failure is then
        // gated down to a quarter of that.
        $this->assertEqualsWithDelta(3.0, $score->rawScore, 0.001);
        $this->assertEqualsWithDelta(0.75, $score->finalScore, 0.001);
        $this->assertSame(['functional' => 0.25], $score->gatePenalties);
    }

    public function testNotApplicableGateEvaluatorDoesNotPenalise(): void
    {
        $calculator = ScoreCalculator::withDefaults();

        $score = $calculator->calculate($this->scenario(), [
            EvaluationResult::notApplicable('functional', 'Cannot be judged.'),
            new EvaluationResult('root_cause', 5.0, true, ''),
            new EvaluationResult('mate_tool_usage', 5.0, true, ''),
            new EvaluationResult('minimality', 5.0, true, ''),
            new EvaluationResult('verification', 5.0, true, ''),
            new EvaluationResult('efficiency', 5.0, true, ''),
        ]);

        $this->assertSame([], $score->gatePenalties);
        $this->assertSame(['functional'], $score->notApplicable);
        $this->assertEqualsWithDelta(5.0, $score->finalScore, 0.001);
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
