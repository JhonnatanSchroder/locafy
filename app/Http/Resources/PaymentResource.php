<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'contract_id' => $this->contract_id, 'charge_id' => $this->charge_id,
            'client' => $this->contract?->client?->name, 'amount' => $this->amount, 'discount_amount' => $this->discount_amount, 'settled_amount' => $this->settledAmount(),
            'paid_at' => $this->paid_at->toIso8601String(), 'method' => $this->method, 'notes' => $this->notes,
        ];
    }
}
