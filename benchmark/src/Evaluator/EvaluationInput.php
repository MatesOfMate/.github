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

use MatesOfMate\Benchmark\Runner\RunOutcome;
use MatesOfMate\Benchmark\Scenario\Scenario;

/**
 * Bundle of inputs supplied to an {@see EvaluatorInterface}.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class EvaluationInput
{
    public function __construct(
        public Scenario $scenario,
        public RunOutcome $outcome,
    ) {
    }
}
