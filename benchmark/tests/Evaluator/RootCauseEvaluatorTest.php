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
use MatesOfMate\Benchmark\Evaluator\EvaluationInput;
use MatesOfMate\Benchmark\Evaluator\RootCauseEvaluator;
use MatesOfMate\Benchmark\Runner\DiffResult;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class RootCauseEvaluatorTest extends TestCase
{
    public function testNoExpectedKeywordsIsNotApplicable(): void
    {
        $outcome = RunOutcomeBuilder::build();

        $result = (new RootCauseEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        // Without declared keywords the category cannot be judged; it is
        // excluded from scoring instead of counting as a zero.
        $this->assertFalse($result->applicable);
        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testAllKeywordsMatchedScoresFive(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: [
                'expected' => ['root_cause' => ['autowiring failure', 'private service']],
            ],
            assistantResult: AssistantRunResult::success(
                stdout: 'I traced the issue to an Autowiring failure caused by a private service.',
                durationMs: 1.0,
            ),
        );

        $result = (new RootCauseEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
    }

    public function testPartialMatchUsesProportion(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: [
                'expected' => ['root_cause' => ['autowiring failure', 'private service']],
            ],
            assistantResult: AssistantRunResult::success(
                stdout: 'It is an autowiring failure.',
                durationMs: 1.0,
            ),
            diff: new DiffResult(diff: '', stat: '', changedFiles: [], additions: 0, deletions: 0),
        );

        $result = (new RootCauseEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(2.5, $result->score);
        $this->assertFalse($result->passed);
        $this->assertSame(['private service'], $result->evidence['missing']);
    }

    public function testSynonymGroupMatchesWhenAnyPhraseOccurs(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: [
                'expected' => ['root_cause' => [
                    ['autowiring failure', 'missing alias'],
                    'private service',
                ]],
            ],
            assistantResult: AssistantRunResult::success(
                stdout: 'The container throws because of a missing alias; the private service is never exposed.',
                durationMs: 1.0,
            ),
        );

        $result = (new RootCauseEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
        $this->assertContains('missing alias', $result->evidence['matched']);
    }

    public function testKeywordsOnlyMatchOnWordBoundaries(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: [
                'expected' => ['root_cause' => ['env']],
            ],
            assistantResult: AssistantRunResult::success(
                stdout: 'The environment loader looked fine to me.',
                durationMs: 1.0,
            ),
        );

        $result = (new RootCauseEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertTrue($result->applicable);
        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testEchoingThePromptEarnsNoCredit(): void
    {
        $prompt = 'Investigate the autowiring failure in the container.';
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: [
                'task' => ['prompt' => $prompt],
                'expected' => ['root_cause' => ['autowiring failure']],
            ],
            assistantResult: AssistantRunResult::success(
                stdout: $prompt,
                durationMs: 1.0,
            ),
        );

        $result = (new RootCauseEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }

    public function testEchoingTheScenarioIdEarnsNoCredit(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: [
                'id' => 'bug.autowiring',
                'expected' => ['root_cause' => ['autowiring']],
            ],
            assistantResult: AssistantRunResult::success(
                stdout: 'Prepared the bug.autowiring workspace and finished.',
                durationMs: 1.0,
            ),
        );

        $result = (new RootCauseEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
    }
}
