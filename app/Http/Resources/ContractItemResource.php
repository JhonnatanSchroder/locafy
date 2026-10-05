<?php

namespace App\Http\Resources;

use App\Enums\BillingPeriod;
use App\Enums\MovementType;
use App\Enums\ProductType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'billing_period' => $this->billing_period->value,
            'billing_period_label' => match ($this->billing_period) {
                BillingPeriod::Day => 'Dia',
                BillingPeriod::Week => 'Semana',
                BillingPeriod::Month => 'Mês',
            },
            'unit_price' => $this->unit_price,
            'current_quantity' => $this->when(
                $this->relationLoaded('product') && $this->product->type === ProductType::Quantity,
                fn (): int => $this->currentQuantity()
            ),
        ];
    }

    private function currentQuantity(): int
    {
        $this->loadMissing('movementItems.movement');

        return $this->movementItems->sum(function ($movementItem): int {
            return match ($movementItem->movement->type) {
                MovementType::Withdrawal => $movementItem->quantity,
                MovementType::Return => -$movementItem->quantity,
            };
        });
    }
}
