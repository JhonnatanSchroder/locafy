<?php

namespace App\Actions\Freights;

use App\Models\Contract;
use App\Models\Freight;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateFreightAction
{
    /**
     * @param  array{quantity: int, unit_amount: numeric-string|float|int, occurred_at: string, notes?: string|null}  $data
     */
    public function handle(Contract $contract, array $data): Freight
    {
        return DB::transaction(function () use ($contract, $data): Freight {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            if (in_array($contract->status->value, ['FINALIZED', 'CANCELLED'])) {
                throw ValidationException::withMessages(['quantity' => 'Contrato encerrado.']);
            }

            return $contract->freights()->create([
                'company_id' => $contract->company_id,
                'quantity' => $data['quantity'],
                'unit_amount' => $data['unit_amount'],
                'occurred_at' => $data['occurred_at'],
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
