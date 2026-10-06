<?php

namespace App\Services;

use App\Models\Contract;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ContractReceivablesService
{
    public function __construct(private ContractFinanceService $finance) {}

    /** @return array<string, mixed> */
    public function data(Contract $contract, bool $detail = false): array
    {
        $repaired = app(ContractLifecycleService::class)->synchronize($contract);
        $contract->status = $repaired->status;
        $contract->ended_at = $repaired->ended_at;
        $contract->loadMissing('client');
        $finance = $this->finance->summarize($contract);
        $today = CarbonImmutable::today(ContractCalculationService::TIMEZONE)->toDateString();
        $due = $contract->next_charge_date?->toDateString();
        $pending = $finance['balance'] !== null && Money::cents($finance['balance']) > 0;
        $days = $pending && $due !== null && $due < $today
            ? (int) CarbonImmutable::parse($due)->diffInDays(CarbonImmutable::parse($today)) : 0;
        $closed = in_array($contract->status->value, ['FINALIZED', 'CANCELLED']);
        $data = [
            'id' => $contract->id, 'contract_id' => $contract->id, 'client' => $contract->client->name,
            'client_id' => $contract->client_id, 'contract_status' => $contract->status->value,
            ...$finance, ...app(ContractLifecycleService::class)->presentation($contract, $finance['balance']), 'next_charge_date' => $due, 'charge_interval_days' => $contract->charge_interval_days,
            'ended_at' => $contract->ended_at?->toIso8601String(),
            'days_overdue' => $closed ? 0 : $days, 'due_today' => ! $closed && $pending && $due === $today,
            'is_collectible' => ! $closed && $pending && ($contract->status->value === 'RETURNED' || ($due !== null && $due <= $today)),
            'financial_status' => $finance['balance'] === null ? 'UNAVAILABLE' : (! $pending ? 'PAID' : (Money::cents($finance['total_paid']) + Money::cents($finance['total_discount']) > 0 ? 'PARTIAL' : 'PENDING')),
            'notes' => $contract->notes,
        ];
        if ($detail) {
            $data['payments'] = $contract->payments->sortByDesc('paid_at')->values()->map(fn ($p) => [
                'id' => $p->id, 'amount' => $p->amount, 'discount_amount' => $p->discount_amount, 'settled_amount' => $p->settledAmount(), 'paid_at' => $p->paid_at->toIso8601String(), 'method' => $p->method, 'notes' => $p->notes, 'charge_id' => $p->charge_id,
            ])->all();
        }

        return $data;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function rows(int $companyId, string $filter = '', string $search = ''): Collection
    {
        $contracts = Contract::query()->where('company_id', $companyId)->whereIn('status', ['ACTIVE', 'RETURNED'])
            ->with(['client', 'payments', 'freights', 'items.product', 'items.movementItems.movement', 'movements.items'])->get();
        $today = CarbonImmutable::today(ContractCalculationService::TIMEZONE)->toDateString();

        return $contracts->map(fn ($contract) => $this->data($contract))->filter(function ($row) use ($filter, $today, $search): bool {
            if ($row['balance'] === null || Money::cents($row['balance']) <= 0) {
                return false;
            }
            if ($search !== '' && ! str_contains(mb_strtolower($row['client']), mb_strtolower($search)) && (string) $row['id'] !== $search) {
                return false;
            }

            return match ($filter) {
                'today' => $row['next_charge_date'] === $today,
                'overdue' => $row['next_charge_date'] !== null && $row['next_charge_date'] < $today,
                'upcoming' => $row['next_charge_date'] !== null && $row['next_charge_date'] > $today,
                'outstanding' => true,
                default => $row['is_collectible'],
            };
        })->sortBy(fn ($row) => $row['next_charge_date'] ?? '0000-00-00')->values();
    }
}
