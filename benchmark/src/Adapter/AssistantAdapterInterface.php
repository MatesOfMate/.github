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
 * Shared execution contract for benchmark assistant backends (Codex, Claude Code, NullAdapter, ...).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface AssistantAdapterInterface
{
    /**
     * Stable adapter identifier matching the `--adapter` CLI option.
     */
    public function name(): string;

    public function run(AssistantRunInput $input): AssistantRunResult;
}
