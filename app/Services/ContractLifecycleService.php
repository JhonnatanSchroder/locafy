<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ContractLifecycleService
{
    public function __construct(private MovementBalanceService $balances) {}

    public function synchronize(Contract $contract): Contract
    {
        return DB::transaction(function () use ($contract): Contract {
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status === ContractStatus::Finalized && $locked->ended_at === null) {
                $locked->update(['ended_at' => $this->returnEnd($locked)]);
            }
            if (in_array($locked->status, [ContractStatus::Finalized, ContractStatus::Cancelled])) {
                return $locked;
            }
            $hasItemsOut = $this->balances->currentQuantities($locked)->contains(fn (int $quantity): bool => $quantity !== 0);
            if ($hasItemsOut) {
                if ($locked->status === ContractStatus::Returned) {
                    $locked->update(['status' => ContractStatus::Active, 'ended_at' => null]);
                }
            } elseif ($locked->movements()->where('type', 'RETURN')->exists()) {
                $locked->update(['status' => ContractStatus::Returned, 'ended_at' => $locked->ended_at ?? $this->returnEnd($locked)]);
            }

            return $locked;
        });
    }

    public function returnEnd(Contract $contract): CarbonImmutable
    {
        $last = $contract->movements()->latest('occurred_at')->latest('id')->first();
        if ($last !== null && $last->type->value === 'RETURN') {
            return CarbonImmutable::instance($last->occurred_at);
        }

        return CarbonImmutable::now(ContractCalculationService::TIMEZONE)->utc();
    }

    public function repairCompany(int $companyId): void
    {
        Contract::query()->where('company_id', $companyId)->whereIn('status', ['ACTIVE', 'RETURNED'])
            ->whereHas('movements', fn ($query) => $query->where('type', 'RETURN'))
            ->each(fn (Contract $contract) => $this->synchronize($contract));
    }

    /** @return array{display_status: string, display_status_label: string, can_finalize: bool} */
    public function presentation(Contract $contract, ?string $balance): array
    {
        $ready = $contract->status === ContractStatus::Returned && $balance === '0.00';
        $pending = $contract->status === ContractStatus::Returned && $balance !== null && $balance !== '0.00';
        $label = match ($contract->status) {
            ContractStatus::Active => 'Ativo',
            ContractStatus::Returned => $ready ? 'Pronto para finalizar' : ($pending ? 'Pendente de pagamento' : 'Devolvido'),
            ContractStatus::Finalized => 'Finalizado',
            ContractStatus::Cancelled => 'Cancelado',
        };

        return ['display_status' => $ready ? 'READY_TO_FINALIZE' : ($pending ? 'PAYMENT_PENDING' : $contract->status->value), 'display_status_label' => $label, 'can_finalize' => $ready];
    }
}
