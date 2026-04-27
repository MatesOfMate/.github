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
use MatesOfMate\Benchmark\Evaluator\ForbiddenChangesEvaluator;
use MatesOfMate\Benchmark\Runner\DiffResult;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ForbiddenChangesEvaluatorTest extends TestCase
{
    public function testNoForbiddenDeclaredPasses(): void
    {
        $outcome = RunOutcomeBuilder::build(
            diff: new DiffResult(diff: '', stat: '', changedFiles: ['a.php'], additions: 5, deletions: 0),
        );

        $result = (new ForbiddenChangesEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(5.0, $result->score);
        $this->assertTrue($result->passed);
    }

    public function testTouchingForbiddenFileFailsHard(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['forbidden_files_changed' => ['composer.lock']]],
            diff: new DiffResult(diff: '', stat: '', changedFiles: ['src/x.php', 'composer.lock'], additions: 1, deletions: 1),
        );

        $result = (new ForbiddenChangesEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertSame(0.0, $result->score);
        $this->assertFalse($result->passed);
        $this->assertSame(['composer.lock'], $result->evidence['violations']);
    }

    public function testRespectsSubstringMatchAcrossSubdirectories(): void
    {
        $outcome = RunOutcomeBuilder::build(
            scenarioOverrides: ['expected' => ['forbidden_files_changed' => ['composer.lock']]],
            diff: new DiffResult(diff: '', stat: '', changedFiles: ['vendor/x/composer.lock'], additions: 1, deletions: 0),
        );

        $result = (new ForbiddenChangesEvaluator())->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertFalse($result->passed);
    }
}
