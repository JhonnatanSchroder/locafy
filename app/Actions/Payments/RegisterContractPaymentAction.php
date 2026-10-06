<?php

namespace App\Actions\Payments;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Payment;
use App\Services\ContractCalculationService;
use App\Services\ContractFinanceService;
use App\Services\ContractLifecycleService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterContractPaymentAction
{
    /** @param array<string, mixed> $data */
    public function handle(Contract $contract, array $data, ?Charge $charge = null): Payment
    {
        return DB::transaction(function () use ($contract, $data, $charge): Payment {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $contract = app(ContractLifecycleService::class)->synchronize($contract);
            if (in_array($contract->status->value, ['FINALIZED', 'CANCELLED'])) {
                throw ValidationException::withMessages(['amount' => 'Contrato encerrado.']);
            }
            if ($charge !== null) {
                $charge = Charge::query()->lockForUpdate()->findOrFail($charge->id);
                if ($charge->contract_id !== $contract->id || $charge->company_id !== $contract->company_id || $charge->status === 'CANCELLED') {
                    throw ValidationException::withMessages(['amount' => 'Cobrança histórica inválida.']);
                }
            }
            $finance = app(ContractFinanceService::class)->summarize($contract);
            $amount = Money::cents((string) $data['amount']);
            $discount = Money::cents((string) ($data['discount_amount'] ?? '0.00'));
            $settled = $amount + $discount;
            if ($finance['balance'] === null || $amount < 0 || $discount < 0 || $settled <= 0 || $settled > Money::cents($finance['balance'])) {
                throw ValidationException::withMessages(['amount' => 'Pagamento/desconto inválido ou superior ao saldo atual do contrato.']);
            }
            $payment = $contract->payments()->create([
                ...$data, 'discount_amount' => $data['discount_amount'] ?? '0.00', 'company_id' => $contract->company_id, 'charge_id' => $charge?->id,
            ]);
            // Updating historical status never determines the contract's financial balance.
            if ($charge !== null) {
                $settledForCharge = $charge->payments()->get()->sum(fn ($p) => Money::cents($p->amount) + Money::cents((string) ($p->discount_amount ?? '0.00')));
                $charge->update(['status' => $settledForCharge >= Money::cents($charge->total_amount) ? 'PAID' : 'PARTIAL']);
            }
            if ($settled === Money::cents($finance['balance']) && $contract->next_charge_date !== null) {
                if ($contract->charge_interval_days < 1 || $contract->charge_interval_days > 365) {
                    throw ValidationException::withMessages(['charge_interval_days' => 'Intervalo de cobrança inválido.']);
                }
                $today = CarbonImmutable::today(ContractCalculationService::TIMEZONE);
                $next = CarbonImmutable::parse($contract->next_charge_date->toDateString(), ContractCalculationService::TIMEZONE);
                do {
                    $next = $next->addDays($contract->charge_interval_days);
                } while ($next->lte($today));
                $contract->update(['next_charge_date' => $next->toDateString()]);
            }

            return $payment;
        });
    }
}
