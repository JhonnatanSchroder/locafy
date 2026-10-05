<?php

namespace App\Actions\Freights;

use App\Models\Contract;
use App\Models\Freight;

class CreateFreightAction
{
    /**
     * @param  array{quantity: int, unit_amount: numeric-string|float|int, occurred_at: string, notes?: string|null}  $data
     */
    public function handle(Contract $contract, array $data): Freight
    {
        return $contract->freights()->create([
            'company_id' => $contract->company_id,
            'quantity' => $data['quantity'],
            'unit_amount' => $data['unit_amount'],
            'occurred_at' => $data['occurred_at'],
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
