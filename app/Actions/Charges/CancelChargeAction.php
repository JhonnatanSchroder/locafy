<?php

namespace App\Actions\Charges;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\Charge;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelChargeAction
{
    public function handle(Charge $charge): void
    {
        DB::transaction(function () use ($charge) {
            $charge = Charge::query()->lockForUpdate()->findOrFail($charge->id);
            if ($charge->payments()->exists()) {
                throw ValidationException::withMessages(['charge' => 'Cobrança com pagamentos não pode ser cancelada.']);
            }
            $charge->update(['status' => 'CANCELLED']);
            if (auth()->user() !== null) {
                app(RecordAdminAuditAction::class)->handle(auth()->user(), 'charge', $charge->id, 'cancelled', ['status' => 'PENDING'], ['status' => 'CANCELLED']);
            }
        });
    }
}
