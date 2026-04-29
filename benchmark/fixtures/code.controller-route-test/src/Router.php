<?php

class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$method.' '.$path] = $handler;
    }

    /**
     * @return array{0: int, 1: string}
     */
    public function dispatch(string $method, string $path): array
    {
        $key = $method.' '.$path;
        if (!isset($this->routes[$key])) {
            return [404, 'Not Found'];
        }

        return ($this->routes[$key])();
    }
}
