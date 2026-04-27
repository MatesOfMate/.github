<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Evaluator;

/**
 * Single deterministic or rule-based judge that scores one aspect of a benchmark run.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface EvaluatorInterface
{
    /**
     * Stable identifier (e.g. `functional`, `root_cause`) matching the scoring weight keys.
     */
    public function name(): string;

    public function evaluate(EvaluationInput $input): EvaluationResult;
}
