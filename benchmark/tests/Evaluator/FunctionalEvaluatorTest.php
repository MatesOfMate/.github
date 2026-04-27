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
use MatesOfMate\Benchmark\Evaluator\FunctionalEvaluator;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class FunctionalEvaluatorTest extends TestCase
{
    public function testNoVerificationCommandsYieldsZero(): void
    {
        $outcome = RunOutcomeBuilder::build(verificationResults: []);

        $result = (new FunctionalEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testAllPassingYieldsFiveAndPasses(): void
    {
        $outcome = RunOutcomeBuilder::build(verificationResults: [
            RunOutcomeBuilder::passingCommand(),
            RunOutcomeBuilder::passingCommand(),
        ]);

        $result = (new FunctionalEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
    }

    public function testHalfPassingScoresProportional(): void
    {
        $outcome = RunOutcomeBuilder::build(verificationResults: [
            RunOutcomeBuilder::passingCommand(),
            RunOutcomeBuilder::failingCommand(),
        ]);

        $result = (new FunctionalEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertEqualsWithDelta(2.5, $result->score, 0.001);
        $this->assertFalse($result->passed);
    }
}
