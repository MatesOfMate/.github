<?php

class OrderService
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    public function placeOrder(int $amountCents): bool
    {
        return $this->gateway->charge($amountCents);
    }
}
