<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Cache;

/**
 * Keeps the last few runs on disk so a grouped response can stay small and the
 * agent can still ask for the parts it needs.
 *
 * Eviction is by count rather than by age: a run is worth keeping because it is
 * one of the last few, not because it happened recently, and a wall-clock rule
 * would throw away the run an agent is still working through if it paused to
 * read some source.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class RunCache
{
    public function __construct(
        private readonly string $cacheDir,
        private readonly string $namespace = 'rector-runs',
        private readonly int $keep = 20,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return string the run id to hand back to the agent
     */
    public function store(array $payload): string
    {
        $dir = $this->directory();
        // Silenced on purpose: the failure is handled right here, and an
        // unsilenced warning would be printed into the tool's own output.
        if (!is_dir($dir) && !@mkdir($dir, 0o700, true) && !is_dir($dir)) {
            throw new \RuntimeException("Unable to create the run cache directory: {$dir}");
        }

        // Microseconds, not just seconds: ids are the sort key for both
        // "newest first" and eviction, and two runs inside the same second
        // would otherwise be ordered by the random suffix — which would evict
        // an arbitrary run rather than the oldest one.
        $now = microtime(true);
        $id = \sprintf(
            '%s-%06d-%s',
            date('Ymd-His', (int) $now),
            (int) round(fmod($now, 1) * 1_000_000),
            bin2hex(random_bytes(3))
        );

        // Write then rename, so a reader never sees a half-written run.
        $tmp = $dir.'/.'.$id.'.tmp';
        file_put_contents($tmp, json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
        rename($tmp, $dir.'/'.$id.'.json');

        $this->evict();

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $id): ?array
    {
        // The id reaches this class from a tool argument, so it must not be
        // able to walk out of the cache directory.
        if (1 !== preg_match('/^[A-Za-z0-9-]+$/', $id)) {
            return null;
        }

        $file = $this->directory().'/'.$id.'.json';
        if (!is_file($file)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, string> the known run ids, newest first
     */
    public function ids(): array
    {
        $files = glob($this->directory().'/*.json') ?: [];
        rsort($files, \SORT_STRING);

        return array_map(static fn (string $f): string => basename($f, '.json'), $files);
    }

    private function evict(): void
    {
        $files = glob($this->directory().'/*.json') ?: [];
        if (\count($files) <= $this->keep) {
            return;
        }

        // Ids start with a sortable timestamp, so string order is age order.
        sort($files, \SORT_STRING);
        foreach (\array_slice($files, 0, \count($files) - $this->keep) as $old) {
            @unlink($old);
        }
    }

    private function directory(): string
    {
        return rtrim($this->cacheDir, '/').'/'.$this->namespace;
    }
}
