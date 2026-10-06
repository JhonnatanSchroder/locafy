<?php

namespace App\Actions\Charges;

use App\Models\Charge;
use App\Models\Contract;
use App\Services\ContractAccrualService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateChargeAction
{
    /** @return array{rental: int, freight: int, calculated_until: string} */
    public function unbilledAmounts(Contract $contract, ?CarbonImmutable $asOf = null): array
    {
        $s = app(ContractAccrualService::class)->summarize($contract, $asOf);
        if ($s['calculation']->rentalTotal === null) {
            throw ValidationException::withMessages(['contract_id' => 'Cálculo incompleto para este contrato.']);
        }
        $previous = $contract->charges()->where('status', '!=', 'CANCELLED')->get();
        $r = Money::cents($s['calculation']->rentalTotal) - $previous->sum(fn ($c) => Money::cents($c->rental_amount));
        $f = Money::cents($s['freight_total']) - $previous->sum(fn ($c) => Money::cents($c->freight_amount));
        if ($r < 0 || $f < 0) {
            throw ValidationException::withMessages(['contract_id' => 'Acumulado inferior às cobranças existentes. Revise os snapshots.']);
        }

        return ['rental' => $r, 'freight' => $f, 'calculated_until' => $s['calculation']->calculatedUntil];
    }

    /** @param array<string, mixed> $data */
    public function handle(Contract $contract, array $data, ?CarbonImmutable $asOf = null): Charge
    {
        return DB::transaction(function () use ($contract, $data, $asOf) {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            if (in_array($contract->status->value, ['CANCELLED', 'FINALIZED'])) {
                throw ValidationException::withMessages(['contract_id' => 'Contrato encerrado.']);
            }
            $amounts = $this->unbilledAmounts($contract, $asOf);
            $r = $amounts['rental'];
            $f = $amounts['freight'];
            if ($r + $f === 0) {
                throw ValidationException::withMessages(['contract_id' => 'Não há valor novo a cobrar.']);
            }

            return $contract->charges()->create(['company_id' => $contract->company_id, 'rental_amount' => Money::format($r), 'freight_amount' => Money::format($f), 'total_amount' => Money::format($r + $f), 'due_date' => $data['due_date'] ?? $contract->next_charge_date ?? today(), 'calculated_until' => $amounts['calculated_until'], 'cycle_due_date' => $data['cycle_due_date'] ?? null, 'status' => 'PENDING', 'notes' => $data['notes'] ?? null]);
        });
    }
}
