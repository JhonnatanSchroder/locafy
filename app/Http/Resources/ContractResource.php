<?php

namespace App\Http\Resources;

use App\Enums\BillingPeriod;
use App\Enums\ContractStatus;
use App\Services\ContractCalculationService;
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
        $calculation = app(ContractCalculationService::class)->calculate($this->resource);

        return [
            'id' => $this->id,
            'number' => $this->id,
            'status' => $this->status->value,
            'status_label' => match ($this->status) {
                ContractStatus::Active => 'Ativo',
                ContractStatus::Returned => 'Devolvido',
                ContractStatus::Finalized => 'Finalizado',
                ContractStatus::Cancelled => 'Cancelado',
            },
            'client' => new ClientResource($this->whenLoaded('client')),
            'worksite_address' => $this->worksite_address,
            'started_at' => $this->started_at?->toJSON(),
            'ended_at' => $this->ended_at?->toJSON(),
            'charge_saturdays' => $this->charge_saturdays,
            'next_charge_date' => $this->next_charge_date?->toDateString(),
            'calculated_until' => $calculation->calculatedUntil,
            'rental_total' => $calculation->rentalTotal,
            'calculation_complete' => $calculation->calculationComplete,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(function ($item) use ($calculation): array {
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
        ];
    }
}
