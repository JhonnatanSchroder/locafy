<?php

namespace App\Actions\Charges;

use App\Actions\Payments\RegisterContractPaymentAction;
use App\Models\Charge;
use App\Models\Payment;

class RegisterPaymentAction
{
    public function __construct(private RegisterContractPaymentAction $payments) {}

    /** @param array<string, mixed> $data */
    public function handle(Charge $charge, array $data): Payment
    {
        return $this->payments->handle($charge->contract, $data, $charge);
    }
}
