<?php

class AccessControl
{
    public function canAccess(User $user, Resource $resource): bool
    {
        // BUG: this returns true for everyone, regardless of ownership or role.
        // Expected: allow only when user is the owner OR has the "admin" role.
        return true;
    }
}
