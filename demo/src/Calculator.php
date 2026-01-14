<?php

namespace App;

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function subtract(int $a, int $b): int
    {
        return $a - $b;
    }

    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    public function divide(int $a, int $b): float
    {
        if ($b === 0) {
            throw new \InvalidArgumentException('Cannot divide by zero');
        }
        
        return $a / $b;
    }

    public function power(int $base, int $exponent): int
    {
        return $base ** $exponent;
    }

    /**
     * This method has intentional PHPStan errors for testing
     */
    public function problematicMethod()
    {
        // Undefined variable
        return $undefinedVariable;

        // Unreachable code
        $x = 10;
        return $x;
    }

    /**
     * Another method with type issues
     */
    public function typeIssueMethod(string $param): int
    {
        // Returning wrong type
        return $param;
    }

    /**
     * Method with unused parameter
     */
    public function unusedParamMethod(int $unused): void
    {
        // Parameter $unused is never used
        echo "Hello World";
    }
}
