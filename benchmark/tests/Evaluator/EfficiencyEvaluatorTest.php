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
use MatesOfMate\Benchmark\Adapter\TokenUsage;
use MatesOfMate\Benchmark\Evaluator\EfficiencyEvaluator;
use MatesOfMate\Benchmark\Evaluator\EvaluationInput;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class EfficiencyEvaluatorTest extends TestCase
{
    public function testNotApplicableWithoutVerificationResults(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(stdout: '', durationMs: 5_000.0),
            verificationResults: [],
        );

        $result = (new EfficiencyEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertFalse($result->applicable);
        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testNotApplicableWhenAnyVerificationCommandFailed(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: '',
                durationMs: 5_000.0,
                tokenUsage: new TokenUsage(1_000, 500),
            ),
            verificationResults: [
                RunOutcomeBuilder::passingCommand(),
                RunOutcomeBuilder::failingCommand(),
            ],
        );

        $result = (new EfficiencyEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertFalse($result->applicable, 'A fast failure is not efficient; the category must be excluded.');
        $this->assertSame(0.0, $result->score);
    }

    public function testFastSuccessfulRunWithFewFreshTokensScoresFive(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: '',
                durationMs: 45_000.0,
                // Huge cache traffic must not count: cache reads are how CLI
                // agents are supposed to work.
                tokenUsage: new TokenUsage(inputTokens: 2_000, outputTokens: 1_000, cachedTokens: 900_000),
            ),
            verificationResults: [RunOutcomeBuilder::passingCommand()],
        );

        $result = (new EfficiencyEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertTrue($result->applicable);
        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
        $this->assertSame(3_000, $result->evidence['fresh_tokens']);
    }

    public function testScoreAveragesDurationAndTokenTiers(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: '',
                // 100s => duration tier 4; 40k fresh tokens => token tier 3.
                durationMs: 100_000.0,
                tokenUsage: new TokenUsage(inputTokens: 30_000, outputTokens: 10_000),
            ),
            verificationResults: [RunOutcomeBuilder::passingCommand()],
        );

        $result = (new EfficiencyEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(3.5, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testSlowRunWithManyFreshTokensScoresOne(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: '',
                durationMs: 600_000.0,
                tokenUsage: new TokenUsage(inputTokens: 150_000, outputTokens: 50_000),
            ),
            verificationResults: [RunOutcomeBuilder::passingCommand()],
        );

        $result = (new EfficiencyEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(1.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testMissingTokenUsageFallsBackToDurationOnly(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(stdout: '', durationMs: 25_000.0),
            verificationResults: [RunOutcomeBuilder::passingCommand()],
        );

        $result = (new EfficiencyEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
    }

    public function testUsesAssistantDurationNotTotalRunDuration(): void
    {
        $outcome = RunOutcomeBuilder::build(
            assistantResult: AssistantRunResult::success(
                stdout: '',
                durationMs: 30_000.0,
                tokenUsage: new TokenUsage(1_000, 500),
            ),
            verificationResults: [RunOutcomeBuilder::passingCommand()],
            // Fixture copy, git plumbing and verification time must not be
            // attributed to the assistant.
            metricsOverrides: ['duration_ms' => 999_999.0],
        );

        $result = (new EfficiencyEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertSame(30_000.0, $result->evidence['assistant_duration_ms']);
    }
}
