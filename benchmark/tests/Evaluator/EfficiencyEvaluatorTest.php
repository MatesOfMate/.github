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

use MatesOfMate\Benchmark\Evaluator\EfficiencyEvaluator;
use MatesOfMate\Benchmark\Evaluator\EvaluationInput;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class EfficiencyEvaluatorTest extends TestCase
{
    public function testFastRunWithFewTokensScoresFive(): void
    {
        $outcome = RunOutcomeBuilder::build(metricsOverrides: [
            'duration_ms' => 5_000.0,
            'total_tokens' => 1_000,
        ]);

        $result = (new EfficiencyEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
    }

    public function testSlowRunWithManyTokensScoresLow(): void
    {
        $outcome = RunOutcomeBuilder::build(metricsOverrides: [
            'duration_ms' => 600_000.0,
            'total_tokens' => 80_000,
        ]);

        $result = (new EfficiencyEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertLessThanOrEqual(2.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testMissingTokensFallsBackToDurationOnly(): void
    {
        $outcome = RunOutcomeBuilder::build(metricsOverrides: [
            'duration_ms' => 25_000.0,
            'total_tokens' => null,
        ]);

        $result = (new EfficiencyEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
    }
}
