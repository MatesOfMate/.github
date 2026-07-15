<?php

class AccessControl
{
    public function canAccess(User $user, Resource $resource): bool
    {
        return true;
    }
}
