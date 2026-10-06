<?php

namespace App\Actions\Freights;

use App\Models\Contract;
use App\Models\Freight;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateFreightAction
{
    /**
     * @param array{
     *     quantity: int,
     *     unit_amount: numeric-string|float|int,
     *     notes?: string|null
     * } $data
     */
    public function handle(Freight $freight, array $data): Freight
    {
        return DB::transaction(function () use ($freight, $data): Freight {
            $contract = Contract::query()->lockForUpdate()->findOrFail($freight->contract_id);
            if (in_array($contract->status->value, ['FINALIZED', 'CANCELLED'])) {
                throw ValidationException::withMessages(['quantity' => 'Contrato encerrado.']);
            }
            $freight->update([
                'quantity' => $data['quantity'],
                'unit_amount' => $data['unit_amount'],
                'notes' => $data['notes'] ?? null,
            ]);

            return $freight->refresh();
        });
    }
}
