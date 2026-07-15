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
    public function testMateDisabledIsNotApplicable(): void
    {
        $outcome = RunOutcomeBuilder::build(mateMetrics: MateMetrics::disabled());

        $result = (new MateToolUsageEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        // A --mate=disabled run cannot be judged on Mate usage: the category is
        // excluded (weights renormalised) instead of dragging the score to 0.
        $this->assertFalse($result->applicable);
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

    public function testNoExpectationWithCallsIsNotApplicable(): void
    {
        $outcome = RunOutcomeBuilder::build(mateMetrics: new MateMetrics(
            enabled: true,
            toolCallCount: 3,
            toolNames: ['x'],
            firstToolCallMs: 0.0,
            toolErrors: 0,
        ));

        $result = (new MateToolUsageEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        // The scenario declares no expected Mate tools, so Mate-tool usage is
        // out of scope: the category is excluded rather than credited, keeping
        // Mate-on and Mate-off scores comparable on non-Mate scenarios.
        $this->assertFalse($result->applicable);
    }

    public function testEnabledButNoToolCallsIsNotApplicableWithoutExpectation(): void
    {
        $outcome = RunOutcomeBuilder::build(mateMetrics: new MateMetrics(
            enabled: true,
            toolCallCount: 0,
            toolNames: [],
            firstToolCallMs: null,
            toolErrors: 0,
        ));

        $result = (new MateToolUsageEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        // Mate provisioned but the scenario expects no tools: not a failure,
        // just out of scope. Penalising it would make merely enabling Mate
        // lower the score of every task native tooling already solves.
        $this->assertFalse($result->applicable);
    }

    public function testAnyOfMatchScoresFive(): void
    {
        $outcome = RunOutcomeBuilder::build(mateMetrics: new MateMetrics(
            enabled: true,
            toolCallCount: 5,
            toolNames: ['monolog-tail', 'Read', 'Edit'],
            firstToolCallMs: 1000.0,
            toolErrors: 0,
            expectedToolsAny: ['monolog-search', 'monolog-tail', 'monolog-list-files'],
            anyToolMatched: true,
        ));

        $result = (new MateToolUsageEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
        $this->assertStringContainsString('monolog-tail', $result->explanation);
    }

    public function testAnyOfWithNoMatchScoresZero(): void
    {
        $outcome = RunOutcomeBuilder::build(mateMetrics: new MateMetrics(
            enabled: true,
            toolCallCount: 5,
            toolNames: ['Read', 'Bash', 'Edit'],
            firstToolCallMs: 1000.0,
            toolErrors: 0,
            expectedToolsAny: ['monolog-search', 'monolog-tail', 'monolog-list-files'],
            anyToolMatched: false,
        ));

        $result = (new MateToolUsageEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }
}
