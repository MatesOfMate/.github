<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Mate;

use MatesOfMate\Benchmark\Runner\Workspace;

/**
 * Bootstraps a workspace so that `vendor/bin/mate serve` can run inside it.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface MateProvisionerInterface
{
    /**
     * Provisions the workspace and returns the absolute path of the MCP
     * configuration file (the value to forward as `--mcp-config`).
     */
    public function provision(Workspace $workspace): string;
}
