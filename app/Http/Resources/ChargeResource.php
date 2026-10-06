<?php

namespace App\Http\Resources;

use App\Models\Charge;
use App\Services\ContractCalculationService;
use App\Services\ContractFinanceService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Charge */
class ChargeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['contract.client', 'payments']);
        $finance = app(ContractFinanceService::class)->summarize($this->contract);
        $paid = $this->payments->sum(fn ($p) => Money::cents($p->amount));
        $discount = $this->payments->sum(fn ($p) => Money::cents((string) ($p->discount_amount ?? '0.00')));
        $settled = $paid + $discount;
        $today = CarbonImmutable::today(ContractCalculationService::TIMEZONE);
        $due = CarbonImmutable::parse($this->due_date->toDateString(), ContractCalculationService::TIMEZONE);
        $pending = in_array($this->status, ['PENDING', 'PARTIAL']) && Money::cents($this->total_amount) > $settled;
        $daysOverdue = $pending && $due->lt($today) ? (int) $due->diffInDays($today) : 0;

        return ['days_overdue' => $daysOverdue, 'due_today' => $pending && $due->isSameDay($today), 'cycle_due_date' => $this->cycle_due_date?->toDateString(), 'id' => $this->id, 'contract_id' => $this->contract_id, 'client' => $this->contract->client->name, 'rental_amount' => $this->rental_amount, 'freight_amount' => $this->freight_amount, 'total_amount' => $this->total_amount, 'paid_amount' => Money::format($paid), 'discount_amount' => Money::format($discount), 'settled_amount' => Money::format($settled), 'balance' => $finance['balance'], 'snapshot_balance' => Money::format(max(0, Money::cents($this->total_amount) - $settled)), 'contract_finance' => $finance, 'due_date' => $this->due_date->toDateString(), 'calculated_until' => $this->calculated_until->toDateString(), 'status' => $this->status, 'notes' => $this->notes, 'payments' => $this->payments->map(fn ($p) => ['id' => $p->id, 'amount' => $p->amount, 'discount_amount' => $p->discount_amount, 'settled_amount' => $p->settledAmount(), 'paid_at' => $p->paid_at->toIso8601String(), 'method' => $p->method, 'notes' => $p->notes])->all()];
    }
}
