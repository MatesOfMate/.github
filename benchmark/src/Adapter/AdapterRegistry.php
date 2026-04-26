<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Adapter;

use MatesOfMate\Benchmark\Adapter\Exception\UnsupportedAdapterException;

/**
 * Resolves the adapter implementation for a given `--adapter` CLI value.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class AdapterRegistry
{
    /**
     * @var array<string, AssistantAdapterInterface>
     */
    private array $adapters = [];

    /**
     * @param iterable<AssistantAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $this->register($adapter);
        }
    }

    public function register(AssistantAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->name()] = $adapter;
    }

    public function has(string $name): bool
    {
        return isset($this->adapters[$name]);
    }

    public function get(string $name): AssistantAdapterInterface
    {
        if (!$this->has($name)) {
            throw UnsupportedAdapterException::forName($name, $this->names());
        }

        return $this->adapters[$name];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->adapters);
        sort($names);

        return $names;
    }
}
