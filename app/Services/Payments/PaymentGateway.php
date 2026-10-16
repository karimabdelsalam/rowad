<?php

namespace App\Services\Payments;

interface PaymentGateway
{
    public function name(): string;

    /** Ask the gateway what happened to a payment (never trust the browser's redirect alone). */
    public function fetch(string $gatewayId): GatewayPayment;
}
