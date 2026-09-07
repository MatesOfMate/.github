<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\PHPUnitExtension\Grouping;

/**
 * Removes the parts of a failure message that carry no information, instead of
 * cutting the message at a byte offset.
 *
 * A byte cut is indifferent to what it removes: on an assertion diff it keeps
 * the unchanged context at the top and throws away the changed lines further
 * down, which is exactly backwards. Dropping unchanged context and vendor stack
 * frames keeps the message short AND keeps the part that says what went wrong.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class MessageStripper
{
    public function __construct(
        private int $contextLines = 2,
    ) {
    }

    /**
     * Cuts a message to a maximum length, shared by every surface that emits a
     * message: a single bound applied inconsistently is no bound at all, since
     * whichever surface is left uncapped can still carry an arbitrarily large
     * value (a single-line assertion diff against a large value has no
     * newline for a "first line" cut to act on).
     */
    public function truncate(string $message, int $max): string
    {
        if (\strlen($message) <= $max) {
            return $message;
        }

        return substr($message, 0, $max - 3).'...';
    }

    public function strip(string $message): string
    {
        $lines = explode("\n", $message);

        // An indented line only means "diff context" inside an actual diff. A
        // message with no diff markers anywhere (an indented SQL statement in
        // an exception message, a var_export dump) is not a diff, and treating
        // its indentation as droppable context would silently delete real
        // content while claiming it was safe, unchanged noise.
        $hasDiffMarker = false;
        foreach ($lines as $line) {
            if ($this->isDiffChange($line)) {
                $hasDiffMarker = true;
                break;
            }
        }

        $kept = [];
        $droppedContext = 0;
        $droppedFrames = 0;
        $pendingContext = [];

        foreach ($lines as $line) {
            if ($this->isVendorFrame($line)) {
                ++$droppedFrames;
                continue;
            }

            if ($this->isDiffChange($line)) {
                // Keep a little context immediately before a change.
                foreach (\array_slice($pendingContext, -$this->contextLines) as $c) {
                    $kept[] = $c;
                }
                $droppedContext -= min(\count($pendingContext), $this->contextLines);
                $pendingContext = [];
                $kept[] = $line;
                continue;
            }

            if ($hasDiffMarker && $this->isDiffContext($line)) {
                $pendingContext[] = $line;
                ++$droppedContext;
                continue;
            }

            $pendingContext = [];
            $kept[] = $line;
        }

        $out = rtrim(implode("\n", $kept));

        $notes = [];
        if ($droppedContext > 0) {
            $notes[] = "{$droppedContext} unchanged diff lines";
        }
        if ($droppedFrames > 0) {
            $notes[] = "{$droppedFrames} vendor stack frames";
        }
        if ([] !== $notes) {
            $out .= "\n[stripped: ".implode(', ', $notes).']';
        }

        return $out;
    }

    private function isDiffChange(string $line): bool
    {
        return 1 === preg_match('/^\s*[-+@]/', $line) && '' !== trim($line, " \t-+");
    }

    private function isDiffContext(string $line): bool
    {
        // Unified-diff context lines start with a single space; PHPUnit's array
        // diffs indent them further.
        return 1 === preg_match('/^\s/', $line) && '' !== trim($line);
    }

    private function isVendorFrame(string $line): bool
    {
        // Delimiter is ~ on purpose: the pattern itself contains '#', which
        // would close a '#'-delimited pattern and make preg_match warn instead
        // of match.
        return 1 === preg_match('~^\s*(?:#\d+\s+)?/.*/vendor/.*\(\d+\)~', $line)
            || 1 === preg_match('~^\s*/.*/vendor/[^\s:]+:\d+$~', $line);
    }
}
