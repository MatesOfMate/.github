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
        $outcome = RunOutcomeBuilder::build();

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testStdoutMentioningTestCommandsEarnsNothing(): void
    {
        // Talk is cheap: claiming to have run phpunit in the final response
        // is not evidence of execution.
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['pass_commands' => ['vendor/bin/phpunit']]],
            assistantResult: AssistantRunResult::success(
                stdout: 'Running vendor/bin/phpunit --filter Foo and confirming green.',
                durationMs: 1.0,
            ),
        );

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
        $this->assertFalse($result->evidence['generic_test_evidence']);
    }

    public function testBashToolCallContainingPassCommandScoresFullAndPasses(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['pass_commands' => ['vendor/bin/phpunit --testsuite unit']]],
            assistantResult: AssistantRunResult::success(
                stdout: 'All done.',
                durationMs: 1.0,
                toolCalls: [
                    new ToolCall('Bash', ['command' => 'cd project && vendor/bin/phpunit --testsuite unit']),
                ],
            ),
        );

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
        $this->assertSame(['vendor/bin/phpunit --testsuite unit'], $result->evidence['executed_pass_commands']);
    }

    public function testPartialPassCommandExecutionScoresProportionally(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['pass_commands' => [
                'php tests/test.php',
                'vendor/bin/phpstan analyse',
            ]]],
            assistantResult: AssistantRunResult::success(
                stdout: '',
                durationMs: 1.0,
                toolCalls: [
                    new ToolCall('Bash', ['command' => 'php tests/test.php']),
                ],
            ),
        );

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(2.5, $result->score);
        $this->assertFalse($result->passed);
        $this->assertSame(['php tests/test.php'], $result->evidence['executed_pass_commands']);
        $this->assertSame(['vendor/bin/phpstan analyse'], $result->evidence['missing_pass_commands']);
    }

    public function testGenericTestCommandWithoutPassCommandScoresHalf(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['pass_commands' => ['php tests/test.php']]],
            assistantResult: AssistantRunResult::success(
                stdout: '',
                durationMs: 1.0,
                toolCalls: [
                    new ToolCall('Bash', ['command' => 'vendor/bin/phpstan analyse --level 8']),
                ],
            ),
        );

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(2.5, $result->score);
        $this->assertFalse($result->passed);
        $this->assertTrue($result->evidence['generic_test_evidence']);
    }

    public function testToolCallNameAloneCountsAsGenericEvidenceOnly(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['pass_commands' => ['php tests/test.php']]],
            assistantResult: AssistantRunResult::success(
                stdout: 'no relevant text',
                durationMs: 1.0,
                toolCalls: [new ToolCall('shell_phpstan')],
            ),
        );

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(2.5, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testNoEvidenceScoresZero(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(stdout: 'I think it works.', durationMs: 1.0),
        );

        $result = (new VerificationEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
        $this->assertFalse($result->evidence['generic_test_evidence']);
    }
}
