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

use MatesOfMate\Benchmark\Scenario\Exception\ScenarioValidationException;
use Symfony\Component\Finder\Finder;

/**
 * Discovers, validates and hydrates scenarios from a directory tree.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScenarioRepository
{
    /**
     * @var array<string, Scenario>|null
     */
    private ?array $scenarios = null;

    public function __construct(
        private readonly string $scenariosDirectory,
        private readonly ScenarioLoader $loader,
        private readonly ScenarioValidator $validator,
    ) {
    }

    /**
     * @return list<Scenario>
     */
    public function all(): array
    {
        return array_values($this->loadAll());
    }

    public function find(string $id): ?Scenario
    {
        return $this->loadAll()[$id] ?? null;
    }

    public function get(string $id): Scenario
    {
        $scenario = $this->find($id);

        if (null === $scenario) {
            throw new \InvalidArgumentException(\sprintf('Scenario "%s" was not found.', $id));
        }

        return $scenario;
    }

    /**
     * @return list<Scenario>
     */
    public function bySuite(string $suite): array
    {
        return array_values(array_filter(
            $this->loadAll(),
            static fn (Scenario $scenario): bool => $scenario->suite === $suite,
        ));
    }

    /**
     * @return list<string>
     */
    public function suites(): array
    {
        $suites = [];
        foreach ($this->loadAll() as $scenario) {
            $suites[$scenario->suite] = true;
        }

        $names = array_keys($suites);
        sort($names);

        return $names;
    }

    /**
     * @return array<string, Scenario>
     */
    private function loadAll(): array
    {
        if (null !== $this->scenarios) {
            return $this->scenarios;
        }

        if (!is_dir($this->scenariosDirectory)) {
            return $this->scenarios = [];
        }

        $finder = (new Finder())
            ->files()
            ->in($this->scenariosDirectory)
            ->name(['*.yaml', '*.yml'])
            ->sortByName();

        $scenarios = [];
        foreach ($finder as $file) {
            $path = $file->getRealPath();
            if (false === $path) {
                continue;
            }

            $data = $this->loader->load($path);
            $errors = $this->validator->validate($data);

            if ([] !== $errors) {
                throw new ScenarioValidationException($path, $errors);
            }

            $scenario = Scenario::fromArray($data, $path);

            if (isset($scenarios[$scenario->id])) {
                throw new \RuntimeException(\sprintf(
                    'Duplicate scenario id "%s" found in "%s" and "%s".',
                    $scenario->id,
                    $scenarios[$scenario->id]->sourcePath,
                    $path,
                ));
            }

            $scenarios[$scenario->id] = $scenario;
        }

        return $this->scenarios = $scenarios;
    }
}
