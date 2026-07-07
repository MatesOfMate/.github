<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Runner\Exception;

/**
 * Thrown when a scenario references a fixture directory that does not exist.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class FixtureNotFoundException extends \RuntimeException
{
    public function __construct(public readonly string $fixturePath)
    {
        parent::__construct(\sprintf('Fixture directory "%s" does not exist or is not readable.', $fixturePath));
    }
}
