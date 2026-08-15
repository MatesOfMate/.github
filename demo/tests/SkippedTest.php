<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class SkippedTest extends TestCase
{
    // This test will be skipped
    #[\PHPUnit\Framework\Attributes\RequiresPhp('>= 9.0')]
    public function testSkippedDueToPHPVersion(): void
    {
        $this->assertTrue(true);
    }

    // This is a passing test
    public function testPassingTest(): void
    {
        $this->assertTrue(true);
    }
}
