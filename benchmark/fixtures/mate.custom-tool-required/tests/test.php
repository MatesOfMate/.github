<?php

require __DIR__.'/../src/Container.php';

$container = new Container();
$register = require __DIR__.'/../src/services.php';
$register($container);

$result = (new ReportRunner($container))->run();
if ('rows=2' !== $result) {
    fwrite(\STDERR, "Expected 'rows=2', got: $result\n");
    exit(1);
}

echo "ok\n";
exit(0);
