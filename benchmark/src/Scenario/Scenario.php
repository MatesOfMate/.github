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

/**
 * Immutable representation of a single benchmark scenario.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
readonly class Scenario
{
    /**
     * @param array<string, mixed> $fixture
     * @param array<string, mixed> $task
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $evaluation
     * @param list<string>         $tags
     */
    public function __construct(
        public string $id,
        public string $suite,
        public ?string $difficulty,
        public array $fixture,
        public array $task,
        public array $expected,
        public array $evaluation,
        public array $tags,
        public string $sourcePath,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $sourcePath): self
    {
        return new self(
            id: (string) $data['id'],
            suite: (string) $data['suite'],
            difficulty: isset($data['difficulty']) ? (string) $data['difficulty'] : null,
            fixture: \is_array($data['fixture'] ?? null) ? $data['fixture'] : [],
            task: \is_array($data['task'] ?? null) ? $data['task'] : [],
            expected: \is_array($data['expected'] ?? null) ? $data['expected'] : [],
            evaluation: \is_array($data['evaluation'] ?? null) ? $data['evaluation'] : [],
            tags: array_values(array_map(strval(...), \is_array($data['tags'] ?? null) ? $data['tags'] : [])),
            sourcePath: $sourcePath,
        );
    }
}
