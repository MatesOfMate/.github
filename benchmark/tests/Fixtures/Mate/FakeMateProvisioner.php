<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Fixtures\Mate;

use MatesOfMate\Benchmark\Mate\MateProvisionerInterface;
use MatesOfMate\Benchmark\Runner\Workspace;
use Symfony\Component\Filesystem\Filesystem;

/**
 * In-memory provisioner used by tests: writes a placeholder mcp.json instead
 * of running `composer install` + `mate init` + `mate discover`, so the runner
 * stays offline and fast.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class FakeMateProvisioner implements MateProvisionerInterface
{
    public function provision(Workspace $workspace): string
    {
        $path = $workspace->path.'/mcp.json';

        (new Filesystem())->dumpFile(
            $path,
            json_encode(
                [
                    'mcpServers' => [
                        'symfony-ai-mate' => [
                            'command' => './vendor/bin/mate',
                            'args' => ['serve', '--force-keep-alive'],
                        ],
                    ],
                ],
                \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT,
            )."\n",
        );

        return $path;
    }
}
