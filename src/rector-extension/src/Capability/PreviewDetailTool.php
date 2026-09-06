<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Capability;

use MatesOfMate\RectorExtension\Cache\RunCache;
use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * Returns the diffs behind a grouped Rector result, without running Rector again.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class PreviewDetailTool
{
    public function __construct(
        private readonly RunCache $cache,
    ) {
    }

    /**
     * @param string      $id    the run id returned by rector-preview or rector-apply
     * @param string|null $rule  return the files changed by one rule group, for example g1
     * @param string|null $file  return the diff of files whose path contains this string
     * @param int         $limit maximum number of diffs to return
     */
    #[MateTool(name: 'rector-run-detail', title: 'Rector Run Detail', description: 'Show the diffs behind a grouped rector-preview or rector-apply result, by run id. Narrow with the rule or file argument.')]
    public function execute(
        string $id,
        ?string $rule = null,
        ?string $file = null,
        int $limit = 20,
    ): string {
        $run = $this->cache->load($id);

        if (null === $run) {
            $known = $this->cache->ids();

            return ResponseEncoder::encode([
                'error' => "Unknown run id: {$id}",
                'hint' => [] === $known
                    ? 'No runs are cached yet. Run rector-preview first.'
                    : 'Cached runs, newest first: '.implode(', ', \array_slice($known, 0, 5)),
            ]);
        }

        /** @var array<int, array<string, mixed>> $groups */
        $groups = $run['groups'] ?? [];
        /** @var array<int, array<string, mixed>> $diffs */
        $diffs = $run['diffs'] ?? [];

        $wanted = null;
        if (null !== $rule) {
            $matched = array_values(array_filter($groups, static fn (array $g): bool => $g['id'] === $rule || $g['rule'] === $rule));
            if ([] === $matched) {
                return ResponseEncoder::encode([
                    'error' => "No rule group {$rule} in this run.",
                    'groups' => array_map(static fn (array $g): string => $g['id'].' '.$g['short'].' ('.$g['count'].')', $groups),
                ]);
            }

            $wanted = $matched[0]['files'];
        }

        $entries = [];
        $truncated = false;
        foreach ($diffs as $entry) {
            $path = (string) ($entry['file'] ?? '');

            if (null !== $wanted && !\in_array($path, $wanted, true)) {
                continue;
            }

            if (null !== $file && !str_contains($path, $file)) {
                continue;
            }

            if (\count($entries) >= $limit) {
                $truncated = true;
                break;
            }

            $entries[] = ['file' => $path, 'diff' => (string) ($entry['diff'] ?? '')];
        }

        $payload = ['run' => $id, 'returned' => \count($entries), 'diffs' => $entries];

        // A short list that does not say it is short reads exactly like a
        // complete one.
        if ($truncated) {
            $payload['truncated'] = true;
            $payload['hint'] = 'More files matched than the limit; raise limit or narrow with rule/file.';
        }

        return ResponseEncoder::encode($payload);
    }
}
