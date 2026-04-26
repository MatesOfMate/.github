<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Adapter;

/**
 * Everything an adapter needs to execute one assistant run inside a workspace.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class AssistantRunInput
{
    /**
     * @param array<string, string> $env
     */
    public function __construct(
        public string $workspacePath,
        public string $prompt,
        public ?string $model = null,
        public bool $mateEnabled = true,
        public array $env = [],
        public int $timeoutSeconds = 600,
    ) {
    }
}
