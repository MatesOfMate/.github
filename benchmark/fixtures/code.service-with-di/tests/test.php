<?php

require __DIR__.'/../src/Container.php';
require __DIR__.'/../src/UserService.php';

$container = new Container();
$container->set(UserService::class, static fn () => new UserService());

$service = $container->get(UserService::class);
$cases = [1 => 'Alice', 2 => 'Bob', 99 => 'unknown'];

foreach ($cases as $id => $expected) {
    $actual = $service->getName($id);
    if ($expected !== $actual) {
        fwrite(\STDERR, \sprintf("getName(%d) expected %s, got %s\n", $id, var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
}

echo "ok\n";
exit(0);
