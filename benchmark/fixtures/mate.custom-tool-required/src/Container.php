<?php

class Container
{
    /** @var array<string, callable> */
    private array $factories = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): object
    {
        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("Service \"$id\" was requested but is not registered.");
        }

        return ($this->factories[$id])($this);
    }
}
