<?php

class PaymentGateway
{
    public function charge(int $amountCents): bool
    {
        return $amountCents > 0;
    }
}
