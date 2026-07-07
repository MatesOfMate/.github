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

use MatesOfMate\Benchmark\Runner\Exception\FixtureNotFoundException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Copies a fixture directory tree into a workspace without mutating the source.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class FixtureCopier
{
    private readonly Filesystem $filesystem;

    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function copy(string $fixturePath, Workspace $workspace): void
    {
        if (!is_dir($fixturePath) || !is_readable($fixturePath)) {
            throw new FixtureNotFoundException($fixturePath);
        }

        $this->filesystem->mirror(
            $fixturePath,
            $workspace->path,
            iterator: null,
            options: ['override' => true, 'copy_on_windows' => true, 'delete' => false],
        );
    }
}
