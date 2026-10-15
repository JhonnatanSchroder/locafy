<?php

namespace App\Actions\Freights;

use App\Actions\Admin\RecordAdminAuditAction;
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
            $before = $freight->only(['quantity', 'unit_amount', 'notes']);
            $freight->update([
                'quantity' => $data['quantity'],
                'unit_amount' => $data['unit_amount'],
                'notes' => $data['notes'] ?? null,
            ]);
            if (auth()->user() !== null) {
                $after = $freight->refresh()->only(array_keys($before));
                $changedBefore = array_intersect_key($before, array_diff_assoc($before, $after));
                if ($changedBefore !== []) {
                    app(RecordAdminAuditAction::class)->handle(auth()->user(), 'freight', $freight->id, 'updated', $changedBefore, array_intersect_key($after, $changedBefore));
                }
            }

            return $freight->refresh();
        });
    }
}
