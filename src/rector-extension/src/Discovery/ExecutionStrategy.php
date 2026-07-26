<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Discovery;

/**
 * Describes how Rector should be executed for the current project.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ExecutionStrategy
{
    /**
     * @param array<int, string> $command
     */
    public function __construct(
        public readonly string $type,
        public readonly array $command,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'command' => $this->command,
        ];
    }
}
