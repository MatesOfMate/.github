<?php

class Resource
{
    public function __construct(public readonly int $id, public readonly int $ownerId)
    {
    }
}
