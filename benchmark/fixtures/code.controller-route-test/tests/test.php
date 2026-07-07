<?php

require __DIR__.'/../src/Router.php';
require __DIR__.'/../src/HelloController.php';

$router = new Router();
$router->add('GET', '/hello', static fn () => (new HelloController())->index());

[$status, $body] = $router->dispatch('GET', '/hello');
if (200 !== $status || 'Welcome' !== $body) {
    fwrite(\STDERR, "Expected [200, 'Welcome'], got: ".var_export([$status, $body], true)."\n");
    exit(1);
}

echo "ok\n";
exit(0);
