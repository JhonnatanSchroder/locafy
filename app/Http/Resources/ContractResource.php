<?php

namespace App\Http\Resources;

use App\Enums\BillingPeriod;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Freight;
use App\Models\Movement;
use App\Services\ContractAccrualService;
use App\Services\ContractFinanceService;
use App\Services\ContractLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Contract) {
            return [];
        }

        $contract = $this->resource;
        $repaired = app(ContractLifecycleService::class)->synchronize($contract);
        $contract->status = $repaired->status;
        $contract->ended_at = $repaired->ended_at;
        $summary = app(ContractAccrualService::class)->summarize($contract);
        $calculation = $summary['calculation'];
        $finance = app(ContractFinanceService::class)->summarize($contract, $summary);

        return [
            'id' => $contract->id,
            'number' => $contract->id,
            'status' => $contract->status->value,
            'status_label' => match ($contract->status) {
                ContractStatus::Active => 'Ativo',
                ContractStatus::Returned => 'Devolvido',
                ContractStatus::Finalized => 'Finalizado',
                ContractStatus::Cancelled => 'Cancelado',
            },
            'client' => new ClientResource($this->whenLoaded('client')),
            'worksite_address' => $contract->worksite_address,
            'started_at' => $contract->started_at->toJSON(),
            'ended_at' => $contract->ended_at?->toJSON(),
            'charge_saturdays' => $contract->charge_saturdays,
            'next_charge_date' => $contract->next_charge_date?->toDateString(),
            'charge_interval_days' => $contract->charge_interval_days,
            'calculated_until' => $calculation->calculatedUntil,
            'rental_total' => $calculation->rentalTotal,
            'calculation_complete' => $calculation->calculationComplete,
            'freight_count' => $summary['freight_count'],
            'freight_total' => $summary['freight_total'],
            'total_accrued' => $summary['total_accrued'],
            ...$finance,
            ...app(ContractLifecycleService::class)->presentation($contract, $finance['balance']),
            'notes' => $contract->notes,
            'attachments_count' => (int) ($contract->attachments_count ?? $contract->attachments()->count()),
            'attachments' => $this->when(
                $request->routeIs('api.v1.contracts.show') && $contract->relationLoaded('attachments'),
                fn () => ContractAttachmentResource::collection($contract->attachments->sortByDesc('created_at')->values())->resolve()
            ),
            'items' => $this->whenLoaded('items', fn () => $contract->items->map(function ($item) use ($calculation): array {
                $itemCalculation = $calculation->item($item->id);

                return [
                    'id' => $item->id,
                    'product' => new ProductResource($item->product),
                    'billing_period' => $item->billing_period->value,
                    'billing_period_label' => match ($item->billing_period) {
                        BillingPeriod::Day => 'Dia',
                        BillingPeriod::Week => 'Semana',
                        BillingPeriod::Month => 'Mês',
                    },
                    'unit_price' => $item->unit_price,
                    'current_quantity' => $itemCalculation?->currentQuantity,
                    'billable_quantity_days' => $itemCalculation?->billableQuantityDays,
                    'accrued_subtotal' => $itemCalculation?->subtotal,
                ];
            })),
            'freights' => $this->when(
                $request->routeIs('api.v1.contracts.show') && $contract->relationLoaded('freights'),
                fn (): array => $this->freightData($contract)
            ),
            'movements' => $this->when(
                $request->routeIs('api.v1.contracts.show') && $contract->relationLoaded('movements'),
                fn (): array => $this->movementData($contract)
            ),
        ];
    }

    /**
     * @return array<int, array{id: int, quantity: int, unit_amount: string, total: string, occurred_at: string|null, notes: string|null}>
     */
    private function freightData(Contract $contract): array
    {
        $freights = [];

        foreach ($contract->freights->sortByDesc('occurred_at')->values() as $freight) {
            if (! $freight instanceof Freight) {
                continue;
            }

            $freights[] = [
                'id' => $freight->id,
                'quantity' => $freight->quantity,
                'unit_amount' => $freight->unit_amount,
                'total' => $this->formatFreightTotal($freight),
                'occurred_at' => $freight->occurred_at->toJSON(),
                'notes' => $freight->notes,
            ];
        }

        return $freights;
    }

    private function formatFreightTotal(Freight $freight): string
    {
        $cents = $freight->quantity * $this->decimalToCents((string) $freight->unit_amount);

        return $this->formatCents($cents);
    }

    /**
     * @return array<int, array{id: int, type: string, type_label: string, occurred_at: string|null, notes: string|null, items: array<int, array{id: int, quantity: int, product: array{id: int, name: string}|null, equipment: array{id: int, name: string}|null}>}>
     */
    private function movementData(Contract $contract): array
    {
        $movements = [];

        foreach ($contract->movements->sortByDesc(fn (Movement $movement): string => sprintf(
            '%012d-%012d',
            $movement->occurred_at?->timestamp ?? 0,
            $movement->id
        ))->values() as $movement) {
            if (! $movement instanceof Movement) {
                continue;
            }

            $movements[] = [
                'id' => $movement->id,
                'type' => $movement->type->value,
                'type_label' => $movement->type->value === 'WITHDRAWAL' ? 'Retirada' : 'Devolução',
                'occurred_at' => $movement->occurred_at?->format('Y-m-d H:i:s'),
                'notes' => $movement->notes,
                'items' => $movement->items->map(fn ($movementItem): array => [
                    'id' => $movementItem->id,
                    'contract_item_id' => $movementItem->contract_item_id,
                    'quantity' => $movementItem->quantity,
                    'product' => $movementItem->contractItem?->product ? [
                        'id' => $movementItem->contractItem->product->id,
                        'name' => $movementItem->contractItem->product->name,
                    ] : null,
                    'equipment' => $movementItem->equipment ? [
                        'id' => $movementItem->equipment->id,
                        'name' => $movementItem->equipment->name,
                    ] : null,
                ])->values()->all(),
            ];
        }

        return $movements;
    }

    private function decimalToCents(string $amount): int
    {
        $normalized = str_contains($amount, '.')
            ? $amount
            : "{$amount}.00";

        [$reais, $cents] = explode('.', $normalized, 2);
        $cents = str_pad(substr($cents, 0, 2), 2, '0');

        return ((int) $reais * 100) + (int) $cents;
    }

    private function formatCents(int $cents): string
    {
        $reais = intdiv($cents, 100);
        $remainingCents = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return "{$reais}.{$remainingCents}";
    }
}
