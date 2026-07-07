<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Scenario;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses a scenario YAML file into an associative array.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScenarioLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('Scenario file "%s" does not exist.', $path));
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Unable to read scenario file "%s".', $path));
        }

        try {
            $parsed = Yaml::parse($contents);
        } catch (ParseException $exception) {
            throw new \RuntimeException(\sprintf('Invalid YAML in scenario "%s": %s', $path, $exception->getMessage()), 0, $exception);
        }

        if (!\is_array($parsed) || ([] !== $parsed && array_is_list($parsed))) {
            throw new \RuntimeException(\sprintf('Scenario "%s" must contain a YAML mapping at the root.', $path));
        }

        /** @var array<string, mixed> $parsed */
        return $parsed;
    }
}
