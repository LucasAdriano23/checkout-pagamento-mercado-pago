<?php

namespace App\Services\Contracts;

use MercadoPago\Resources\Payment;

interface PaymentGatewayClient
{
    public function charge(array $payload, string $idempotencyKey): Payment;
}
