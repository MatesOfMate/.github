<?php

require_once __DIR__.'/Container.php';
require_once __DIR__.'/PaymentGateway.php';
require_once __DIR__.'/OrderService.php';

return static function (Container $c): void {
    // BUG: OrderService depends on PaymentGateway, but PaymentGateway is never registered.
    // The verification test asks the container for an OrderService — this currently fails.
    $c->set(OrderService::class, static fn (Container $c) => new OrderService($c->get(PaymentGateway::class)));
};
