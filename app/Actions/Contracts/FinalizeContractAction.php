<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\ContractFinanceService;
use App\Services\ContractLifecycleService;
use App\Services\MovementBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeContractAction
{
    public function handle(Contract $contract): Contract
    {
        return DB::transaction(function () use ($contract): Contract {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($contract->status === ContractStatus::Finalized) {
                throw ValidationException::withMessages(['status' => 'Este contrato já foi finalizado.']);
            }
            if ($contract->status === ContractStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => 'Um contrato cancelado não pode ser finalizado.']);
            }
            if (app(MovementBalanceService::class)->currentQuantities($contract)->contains(fn (int $quantity): bool => $quantity !== 0)) {
                throw ValidationException::withMessages(['status' => 'Ainda existem peças ou equipamentos fora. Devolva todos os itens antes de finalizar.']);
            }
            if ($contract->ended_at === null) {
                $contract->ended_at = Carbon::instance(app(ContractLifecycleService::class)->returnEnd($contract));
            }
            $finance = app(ContractFinanceService::class)->summarize($contract);
            if ($finance['balance'] === null) {
                throw ValidationException::withMessages(['status' => 'Não foi possível calcular o saldo atual deste contrato.']);
            }
            if ($finance['balance'] !== '0.00') {
                throw ValidationException::withMessages(['status' => 'Quite o saldo atual de R$ '.str_replace('.', ',', $finance['balance']).' antes de finalizar.']);
            }
            $contract->status = ContractStatus::Finalized;
            $contract->save();

            return $contract->refresh();
        });
    }
}
