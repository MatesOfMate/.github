<?php

require_once __DIR__.'/Container.php';
require_once __DIR__.'/PaymentGateway.php';
require_once __DIR__.'/OrderService.php';

return static function (Container $c): void {
    $c->set(OrderService::class, static fn (Container $c) => new OrderService($c->get(PaymentGateway::class)));
};
