<?php

require __DIR__.'/../src/Container.php';

$container = new Container();
$register = require __DIR__.'/../src/services.php';
$register($container);

$service = $container->get(OrderService::class);
if (!$service->placeOrder(2500)) {
    fwrite(\STDERR, "OrderService::placeOrder did not succeed.\n");
    exit(1);
}

echo "ok\n";
exit(0);
