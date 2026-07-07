<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Adapter\Exception;

/**
 * Thrown when the requested adapter is unknown or not yet wired up.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class UnsupportedAdapterException extends \RuntimeException
{
    /**
     * @param list<string> $available
     */
    public static function forName(string $name, array $available): self
    {
        return new self(\sprintf(
            'Adapter "%s" is not registered. Available adapters: %s.',
            $name,
            [] === $available ? '(none)' : implode(', ', $available),
        ));
    }
}
