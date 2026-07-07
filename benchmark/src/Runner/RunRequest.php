<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Runner;

use MatesOfMate\Benchmark\Adapter\AssistantAdapterInterface;
use MatesOfMate\Benchmark\Scenario\Scenario;

/**
 * Parameters for a single scenario attempt.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class RunRequest
{
    public function __construct(
        public Scenario $scenario,
        public AssistantAdapterInterface $adapter,
        public string $runId,
        public int $attempt = 1,
        public ?string $model = null,
        public bool $mateEnabled = true,
        public bool $keepWorkspace = false,
    ) {
    }
}
