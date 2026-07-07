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

use MatesOfMate\Benchmark\Mate\MateConfiguration;

/**
 * Everything an adapter needs to execute one assistant run inside a workspace.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class AssistantRunInput
{
    public MateConfiguration $mateConfig;

    /**
     * @param array<string, string> $env
     */
    public function __construct(
        public string $workspacePath,
        public string $prompt,
        public ?string $model = null,
        ?MateConfiguration $mateConfig = null,
        public array $env = [],
        public int $timeoutSeconds = 600,
    ) {
        $this->mateConfig = $mateConfig ?? MateConfiguration::disabled();
    }

    /**
     * Convenience accessor mirroring the `--mate=enabled|disabled` CLI semantics.
     */
    public function isMateEnabled(): bool
    {
        return $this->mateConfig->enabled;
    }
}
