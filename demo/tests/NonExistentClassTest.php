<?php

namespace App\Tests;

use App\NonExistentClass;
use PHPUnit\Framework\TestCase;

class NonExistentClassTest extends TestCase
{
    public function testNonExistentClass(): void
    {
        $object = new NonExistentClass();
        $this->assertTrue(true); // This line will never be reached
    }
}
