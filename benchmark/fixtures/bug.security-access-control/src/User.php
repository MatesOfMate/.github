<?php

class User
{
    /**
     * @param list<string> $roles
     */
    public function __construct(public readonly int $id, public readonly array $roles = [])
    {
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->roles, true);
    }
}
