<?php

namespace App\Http\Resources;

use App\Models\Movement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Movement) {
            return [];
        }

        $movement = $this->resource;

        return [
            'id' => $movement->id,
            'type' => $movement->type->value,
            'type_label' => $movement->type->value === 'WITHDRAWAL' ? 'Retirada' : 'Devolução',
            'occurred_at' => $movement->occurred_at?->format('Y-m-d H:i:s'),
            'notes' => $movement->notes,
            'contract' => $movement->contract ? [
                'id' => $movement->contract->id,
                'number' => $movement->contract->id,
                'status' => $movement->contract->status->value,
                'worksite_address' => $movement->contract->worksite_address,
                'client' => $movement->contract->client ? [
                    'id' => $movement->contract->client->id,
                    'name' => $movement->contract->client->name,
                    'phone' => $movement->contract->client->phone,
                ] : null,
            ] : null,
            'client' => $movement->contract?->client ? [
                'id' => $movement->contract->client->id,
                'name' => $movement->contract->client->name,
                'phone' => $movement->contract->client->phone,
            ] : null,
            'items' => $movement->items->map(fn ($movementItem): array => [
                'id' => $movementItem->id,
                'contract_item_id' => $movementItem->contract_item_id,
                'quantity' => $movementItem->quantity,
                'product' => $movementItem->contractItem?->product ? [
                    'id' => $movementItem->contractItem->product->id,
                    'name' => $movementItem->contractItem->product->name,
                    'type' => $movementItem->contractItem->product->type->value,
                ] : null,
                'equipment' => $movementItem->equipment ? [
                    'id' => $movementItem->equipment->id,
                    'name' => $movementItem->equipment->name,
                ] : null,
            ])->values()->all(),
        ];
    }
}
