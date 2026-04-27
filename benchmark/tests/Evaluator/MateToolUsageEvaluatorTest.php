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

use MatesOfMate\Benchmark\Evaluator\EvaluationInput;
use MatesOfMate\Benchmark\Evaluator\MateToolUsageEvaluator;
use MatesOfMate\Benchmark\Mate\MateMetrics;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class MateToolUsageEvaluatorTest extends TestCase
{
    public function testMateDisabledScoresZero(): void
    {
        $outcome = RunOutcomeBuilder::build(mateMetrics: MateMetrics::disabled());

        $result = (new MateToolUsageEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testAllExpectedToolsUsedScoresFive(): void
    {
        $outcome = RunOutcomeBuilder::build(mateMetrics: new MateMetrics(
            enabled: true,
            toolCallCount: 4,
            toolNames: ['symfony_logs', 'symfony_container'],
            firstToolCallMs: 1500.0,
            toolErrors: 0,
            expectedTools: ['symfony_logs', 'symfony_container'],
            missingExpectedTools: [],
        ));

        $result = (new MateToolUsageEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
    }

    public function testPartialMatchScoresProportional(): void
    {
        $outcome = RunOutcomeBuilder::build(mateMetrics: new MateMetrics(
            enabled: true,
            toolCallCount: 1,
            toolNames: ['symfony_logs'],
            firstToolCallMs: 1500.0,
            toolErrors: 0,
            expectedTools: ['symfony_logs', 'symfony_container'],
            missingExpectedTools: ['symfony_container'],
        ));

        $result = (new MateToolUsageEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(2.5, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testNoExpectationButCallsObservedYieldsModerateScore(): void
    {
        $outcome = RunOutcomeBuilder::build(mateMetrics: new MateMetrics(
            enabled: true,
            toolCallCount: 3,
            toolNames: ['x'],
            firstToolCallMs: 0.0,
            toolErrors: 0,
        ));

        $result = (new MateToolUsageEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(4.0, $result->score);
        $this->assertTrue($result->passed);
    }

    public function testEnabledButNoToolCallsScoresLow(): void
    {
        $outcome = RunOutcomeBuilder::build(mateMetrics: new MateMetrics(
            enabled: true,
            toolCallCount: 0,
            toolNames: [],
            firstToolCallMs: null,
            toolErrors: 0,
        ));

        $result = (new MateToolUsageEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(1.0, $result->score);
        $this->assertFalse($result->passed);
    }
}
