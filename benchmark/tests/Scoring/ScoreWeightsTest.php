<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Scoring;

use MatesOfMate\Benchmark\Scenario\Scenario;
use MatesOfMate\Benchmark\Scoring\ScoreWeights;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScoreWeightsTest extends TestCase
{
    public function testDefaultWeightsSumToOne(): void
    {
        $defaults = ScoreWeights::defaults()->normalized();

        $this->assertEqualsWithDelta(1.0, array_sum($defaults->weights), 0.0001);
    }

    public function testFromArrayNormalisesPercentages(): void
    {
        $weights = ScoreWeights::fromArray([
            'functional' => 40,
            'root_cause' => 20,
            'mate_tool_usage' => 15,
            'minimality' => 10,
            'verification' => 10,
            'efficiency' => 5,
        ]);

        $this->assertEqualsWithDelta(1.0, array_sum($weights->weights), 0.0001);
        $this->assertEqualsWithDelta(0.40, $weights->weights['functional'], 0.0001);
        $this->assertEqualsWithDelta(0.05, $weights->weights['efficiency'], 0.0001);
    }

    public function testFromArrayFallsBackOnEmptyOrInvalidInput(): void
    {
        $defaults = ScoreWeights::defaults();

        $this->assertSame($defaults, ScoreWeights::fromArray([], $defaults));
        $this->assertSame($defaults, ScoreWeights::fromArray(['functional' => -1.0], $defaults));
    }

    public function testFromScenarioReadsScenarioWeights(): void
    {
        $scenario = Scenario::fromArray([
            'id' => 'weights.test',
            'suite' => 'unit',
            'fixture' => ['path' => '/tmp'],
            'task' => ['prompt' => 'x'],
            'expected' => [],
            'evaluation' => [
                'weights' => [
                    'functional' => 60,
                    'root_cause' => 40,
                ],
            ],
        ], '/virtual/weights.yaml');

        $weights = ScoreWeights::fromScenario($scenario);

        $this->assertEqualsWithDelta(0.6, $weights->weights['functional'], 0.0001);
        $this->assertEqualsWithDelta(0.4, $weights->weights['root_cause'], 0.0001);
    }
}
