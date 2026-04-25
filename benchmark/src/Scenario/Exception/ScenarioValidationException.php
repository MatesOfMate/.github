<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Scenario\Exception;

/**
 * Thrown when a scenario file fails JSON-schema validation.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScenarioValidationException extends \RuntimeException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly string $sourcePath,
        public readonly array $errors,
    ) {
        $message = \sprintf(
            "Scenario \"%s\" failed validation:\n  - %s",
            $sourcePath,
            implode("\n  - ", $errors),
        );

        parent::__construct($message);
    }
}
