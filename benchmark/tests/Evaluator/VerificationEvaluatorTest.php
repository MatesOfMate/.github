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

use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Adapter\ToolCall;
use MatesOfMate\Benchmark\Evaluator\EvaluationInput;
use MatesOfMate\Benchmark\Evaluator\VerificationEvaluator;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class VerificationEvaluatorTest extends TestCase
{
    public function testNoAssistantResultScoresZero(): void
    {
        $outcome = RunOutcomeBuilder::build(assistantResult: null);

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testStdoutMentioningPhpunitYieldsHighScore(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: "Running vendor/bin/phpunit --filter Foo and confirming green.",
                durationMs: 1.0,
            ),
        );

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertGreaterThanOrEqual(4.0, $result->score);
        $this->assertTrue($result->passed);
    }

    public function testToolCallNamesContributeToVerification(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: 'no relevant text',
                durationMs: 1.0,
                toolCalls: [new ToolCall('shell_phpstan')],
            ),
        );

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertTrue($result->passed);
    }

    public function testNoEvidenceYieldsLowScore(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(stdout: 'I think it works.', durationMs: 1.0),
        );

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(1.0, $result->score);
        $this->assertFalse($result->passed);
    }
}
