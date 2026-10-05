<?php

namespace App\Actions\Movements;

use App\Models\Movement;
use App\Services\MovementTimelineValidator;
use Illuminate\Support\Facades\DB;

class UpdateMovementAction
{
    public function __construct(private MovementTimelineValidator $timeline) {}

    /**
     * @param  array{occurred_at: string, notes?: string|null, items: array<int, array{contract_item_id: int, quantity: int, equipment_id?: int|null}>}  $data
     */
    public function handle(Movement $movement, array $data): Movement
    {
        $movement->loadMissing('contract.company');
        $items = collect($data['items'])
            ->filter(fn (array $item): bool => (int) ($item['quantity'] ?? 0) > 0)
            ->values()
            ->all();

        $this->timeline->validateMovementPayload(
            $movement->contract,
            $movement->type->value,
            $data['occurred_at'],
            $items,
            $movement
        );

        return DB::transaction(function () use ($movement, $data, $items): Movement {
            $movement->update([
                'occurred_at' => $data['occurred_at'],
                'notes' => $data['notes'] ?? null,
            ]);

            $movement->items()->delete();

            foreach ($items as $item) {
                $movement->items()->create([
                    'contract_item_id' => $item['contract_item_id'],
                    'quantity' => $item['quantity'],
                    'equipment_id' => $item['equipment_id'] ?? null,
                ]);
            }

            return $movement->load(['contract.client', 'items.contractItem.product']);
        });
    }
}
