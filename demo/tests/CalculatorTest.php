<?php

namespace App\Tests;

use App\Calculator;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
    private Calculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new Calculator();
    }

    // Passing tests
    public function testAdd(): void
    {
        $this->assertEquals(5, $this->calculator->add(2, 3));
        $this->assertEquals(0, $this->calculator->add(0, 0));
        $this->assertEquals(-1, $this->calculator->add(2, -3));
    }

    public function testSubtract(): void
    {
        $this->assertEquals(1, $this->calculator->subtract(3, 2));
        $this->assertEquals(0, $this->calculator->subtract(5, 5));
        $this->assertEquals(-5, $this->calculator->subtract(0, 5));
    }

    public function testMultiply(): void
    {
        $this->assertEquals(6, $this->calculator->multiply(2, 3));
        $this->assertEquals(0, $this->calculator->multiply(5, 0));
        $this->assertEquals(-10, $this->calculator->multiply(5, -2));
    }

    public function testDivide(): void
    {
        $this->assertEquals(2.5, $this->calculator->divide(5, 2));
        $this->assertEquals(1.0, $this->calculator->divide(3, 3));
        $this->assertEquals(-2.0, $this->calculator->divide(10, -5));
    }

    public function testDivideByZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot divide by zero');
        $this->calculator->divide(5, 0);
    }

    // Failing test - intentional error to demonstrate failing tests
    public function testAddFailing(): void
    {
        // This test will fail because the expected result is wrong
        $this->assertEquals(10, $this->calculator->add(2, 3)); // Should be 5, not 10
    }

    // Erroring test - intentional error to demonstrate erroring tests
    public function testDivideErroring(): void
    {
        // This test will error because we're not handling the exception properly
        $result = $this->calculator->divide(10, 0); // This should throw an exception
        $this->assertEquals(0, $result); // This line will never be reached
    }

    // Another passing test
    public function testPower(): void
    {
        $this->assertEquals(8, $this->calculator->power(2, 3));
        $this->assertEquals(1, $this->calculator->power(5, 0));
        $this->assertEquals(0, $this->calculator->power(0, 5));
    }

    /**
     * Test with intentional PHPStan errors
     */
    public function testPhpstanErrors(): void
    {
        // Undefined method call
        $result = $this->calculator->nonExistentMethod();
        $this->assertTrue($result);

        // Wrong parameter type
        $this->calculator->typeIssueMethod(123); // Should be string, not int

        // Calling method with unused parameter
        $this->calculator->unusedParamMethod(42);
    }
}
