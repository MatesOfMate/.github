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
use MatesOfMate\Benchmark\Evaluator\EvaluationPipeline;
use MatesOfMate\Benchmark\Evaluator\EvaluationResult;
use MatesOfMate\Benchmark\Evaluator\EvaluatorInterface;
use MatesOfMate\Benchmark\Tests\Evaluator\Support\RunOutcomeBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class EvaluationPipelineTest extends TestCase
{
    public function testRunsEveryRegisteredEvaluator(): void
    {
        $pipeline = new EvaluationPipeline([
            $this->stubEvaluator('a', 5.0),
            $this->stubEvaluator('b', 3.0),
        ]);

        $outcome = RunOutcomeBuilder::build();
        $results = $pipeline->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertCount(2, $results);
        $this->assertSame(['a', 'b'], array_map(static fn (EvaluationResult $r): string => $r->name, $results));
    }

    public function testWrapsEvaluatorExceptionsAsFailingResults(): void
    {
        $pipeline = new EvaluationPipeline([
            $this->throwingEvaluator('boom'),
            $this->stubEvaluator('ok', 4.0),
        ]);

        $outcome = RunOutcomeBuilder::build();
        $results = $pipeline->evaluate(new EvaluationInput($outcome->scenario, $outcome));

        $this->assertCount(2, $results);
        $this->assertSame(0.0, $results[0]->score);
        $this->assertFalse($results[0]->passed);
        $this->assertStringContainsString('boom', $results[0]->explanation);
        $this->assertSame(4.0, $results[1]->score);
    }

    public function testDefaultEvaluatorsCoverAllScoringCategories(): void
    {
        $names = array_map(static fn (EvaluatorInterface $e): string => $e->name(), EvaluationPipeline::defaultEvaluators());

        foreach (['functional', 'root_cause', 'mate_tool_usage', 'minimality', 'verification', 'efficiency'] as $expected) {
            $this->assertContains($expected, $names);
        }
        $this->assertContains('forbidden_changes', $names);
    }

    private function stubEvaluator(string $name, float $score): EvaluatorInterface
    {
        return new readonly class($name, $score) implements EvaluatorInterface {
            public function __construct(private string $name, private float $score)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function evaluate(EvaluationInput $input): EvaluationResult
            {
                return new EvaluationResult($this->name, $this->score, true, '');
            }
        };
    }

    private function throwingEvaluator(string $message): EvaluatorInterface
    {
        return new readonly class($message) implements EvaluatorInterface {
            public function __construct(private string $message)
            {
            }

            public function name(): string
            {
                return 'throwing';
            }

            public function evaluate(EvaluationInput $input): EvaluationResult
            {
                throw new \RuntimeException($this->message);
            }
        };
    }
}
