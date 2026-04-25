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

use Symfony\Component\Filesystem\Filesystem;

/**
 * Creates and destroys isolated workspace directories.
 *
 * Workspaces live under `<rootDirectory>/runs/<runId>/<scenarioId>/<attempt>/workspace/`,
 * giving every repeat of a scenario its own clean copy.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class WorkspaceFactory
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $rootDirectory,
        ?Filesystem $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function create(string $runId, string $scenarioId, int $attempt = 1, bool $keep = false): Workspace
    {
        if ($attempt < 1) {
            throw new \InvalidArgumentException('Workspace attempt number must be >= 1.');
        }

        $path = \sprintf(
            '%s/runs/%s/%s/%d/workspace',
            rtrim($this->rootDirectory, '/'),
            $runId,
            $scenarioId,
            $attempt,
        );

        if (is_dir($path)) {
            $this->filesystem->remove($path);
        }

        $this->filesystem->mkdir($path, 0o755);

        return new Workspace(
            path: $path,
            runId: $runId,
            scenarioId: $scenarioId,
            attempt: $attempt,
            keep: $keep,
        );
    }

    public function destroy(Workspace $workspace): void
    {
        if ($workspace->keep) {
            return;
        }

        if (is_dir($workspace->path)) {
            $this->filesystem->remove($workspace->path);
        }
    }

    /**
     * Generates a sortable, human-readable run id.
     */
    public function generateRunId(?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable('now');

        return $now->format('Ymd-His').'-'.bin2hex(random_bytes(3));
    }
}
