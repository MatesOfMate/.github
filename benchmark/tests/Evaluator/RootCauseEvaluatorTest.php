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
    public function testNoExpectedKeywordsScoresZero(): void
    {
        $outcome = RunOutcomeBuilder::build();

        $result = (new RootCauseEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

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
}
