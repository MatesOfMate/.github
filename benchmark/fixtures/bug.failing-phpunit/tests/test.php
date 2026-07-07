<?php

require __DIR__.'/../src/Calculator.php';

$calc = new Calculator();
$cases = [[1, 2, 3], [0, 0, 0], [-5, 10, 5]];

foreach ($cases as [$a, $b, $expected]) {
    $actual = $calc->add($a, $b);
    if ($actual !== $expected) {
        fwrite(\STDERR, "add($a, $b) expected $expected, got $actual\n");
        exit(1);
    }
}

echo "ok\n";
exit(0);
