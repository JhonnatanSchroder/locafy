<?php

namespace App\Actions\Contracts;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteContractAction
{
    public function handle(Contract $contract, User $user, RecordAdminAuditAction $audit): void
    {
        DB::transaction(function () use ($contract, $user, $audit): void {
            $contract = Contract::query()
                ->where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($contract->id);
            $before = ['status' => $contract->status?->value ?? $contract->status];
            $hasHistory = $contract->movements()->exists()
                || $contract->payments()->exists()
                || $contract->freights()->exists()
                || $contract->charges()->exists()
                || $contract->attachments()->exists()
                || $contract->items()->whereHas('movementItems')->exists();

            if ($hasHistory) {
                throw ValidationException::withMessages(['contract' => 'Este contrato possui histórico de movimentações, pagamentos ou outras operações e não pode ser excluído definitivamente.']);
            }

            $contract->items()->delete();
            $audit->handle($user, 'contract', $contract->id, 'deleted', $before, null);
            $contract->delete();
        });
    }
}
