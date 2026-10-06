<?php

namespace App\Services;

use App\Data\ContractCalculationResult;
use App\Models\Contract;
use App\Support\Money;

class ContractFinanceService
{
    /**
     * @param  array{calculation: ContractCalculationResult, freight_count: int, freight_total: string, total_accrued: string|null}|null  $accrual
     * @return array{rental_total: string|null, freight_total: string, total_accrued: string|null, total_paid: string, balance: string|null, financial_balance: string|null}
     */
    public function summarize(Contract $contract, ?array $accrual = null): array
    {
        $accrual ??= app(ContractAccrualService::class)->summarize($contract);
        $contract->loadMissing('payments');
        $paid = $contract->payments->sum(fn ($payment) => Money::cents($payment->amount));
        $balance = $accrual['total_accrued'] === null ? null : Money::format(max(0, Money::cents($accrual['total_accrued']) - $paid));

        return ['rental_total' => $accrual['calculation']->rentalTotal, 'freight_total' => $accrual['freight_total'], 'total_accrued' => $accrual['total_accrued'], 'total_paid' => Money::format($paid), 'balance' => $balance, 'financial_balance' => $balance];
    }
}
