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

/**
 * Tracks workspace changes via a local git repository and produces diff summaries.
 *
 * Lifecycle:
 *  - {@see initialize()}    immediately after the workspace is created.
 *  - {@see seal()}          after `setup` commands have completed; this is the baseline.
 *  - {@see collect()}       after assistant execution; produces a {@see DiffResult}.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class GitDiffCollector
{
    public const BASELINE_MESSAGE = 'benchmark baseline';

    public function __construct(
        private readonly CommandExecutor $executor,
    ) {
    }

    public function initialize(Workspace $workspace): void
    {
        $env = $this->gitEnv();

        $this->executor->mustExecute('git init -q', $workspace->path, 'git init', env: $env);
        $this->executor->mustExecute('git config user.email "benchmark@matesofmate.dev"', $workspace->path, 'git config', env: $env);
        $this->executor->mustExecute('git config user.name "Benchmark"', $workspace->path, 'git config', env: $env);
        $this->executor->mustExecute('git config commit.gpgsign false', $workspace->path, 'git config', env: $env);
    }

    public function seal(Workspace $workspace): void
    {
        $env = $this->gitEnv();

        $this->executor->mustExecute('git add -A', $workspace->path, 'git add', env: $env);
        $this->executor->mustExecute(
            \sprintf('git commit --allow-empty -q -m %s', escapeshellarg(self::BASELINE_MESSAGE)),
            $workspace->path,
            'git commit',
            env: $env,
        );
    }

    public function collect(Workspace $workspace): DiffResult
    {
        $env = $this->gitEnv();

        $this->executor->mustExecute('git add -A --intent-to-add', $workspace->path, 'git add', env: $env);

        $diff = $this->executor->mustExecute('git diff HEAD', $workspace->path, 'git diff', env: $env);
        $stat = $this->executor->mustExecute('git diff --numstat HEAD', $workspace->path, 'git diff --numstat', env: $env);

        [$files, $additions, $deletions] = $this->parseNumstat($stat->stdout);

        return new DiffResult(
            diff: $diff->stdout,
            stat: $stat->stdout,
            changedFiles: $files,
            additions: $additions,
            deletions: $deletions,
        );
    }

    /**
     * @return array{0: list<string>, 1: int, 2: int}
     */
    private function parseNumstat(string $stat): array
    {
        $files = [];
        $additions = 0;
        $deletions = 0;

        foreach (preg_split('/\R/', trim($stat)) ?: [] as $line) {
            if ('' === $line) {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 3);
            if (null === $parts || 3 !== \count($parts)) {
                continue;
            }

            [$add, $del, $file] = $parts;
            if (ctype_digit($add)) {
                $additions += (int) $add;
            }
            if (ctype_digit($del)) {
                $deletions += (int) $del;
            }
            $files[] = $file;
        }

        return [$files, $additions, $deletions];
    }

    /**
     * Isolate git from the user's global config and signing setup.
     *
     * @return array<string, string>
     */
    private function gitEnv(): array
    {
        return [
            'GIT_TERMINAL_PROMPT' => '0',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
        ];
    }
}
