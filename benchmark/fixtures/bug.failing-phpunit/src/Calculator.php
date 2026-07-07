<?php

class Calculator
{
    public function add(int $a, int $b): int
    {
        // BUG: subtracting instead of adding.
        return $a - $b;
    }
}
