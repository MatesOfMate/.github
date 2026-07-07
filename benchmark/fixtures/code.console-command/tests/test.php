<?php

require __DIR__.'/../src/Command.php';

$output = (new Command())->run();
if ('hello world' !== $output) {
    fwrite(\STDERR, "Expected 'hello world', got: ".var_export($output, true)."\n");
    exit(1);
}

echo "ok\n";
exit(0);
