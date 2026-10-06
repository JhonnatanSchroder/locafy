<?php

namespace App\Actions\Movements;

use App\Models\Contract;
use App\Models\Movement;
use App\Services\ContractLifecycleService;
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
            ->filter(fn (array $item): bool => (int) $item['quantity'] > 0)
            ->values()
            ->all();

        return DB::transaction(function () use ($movement, $data, $items): Movement {
            $contract = Contract::query()->lockForUpdate()->findOrFail($movement->contract_id);
            $this->timeline->validateMovementPayload($contract, $movement->type->value, $data['occurred_at'], $items, $movement);
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

            // Recompute the physical end after an explicit correction of a returned movement.
            if ($contract->status->value === 'RETURNED') {
                $contract->update(['ended_at' => null]);
            }
            app(ContractLifecycleService::class)->synchronize($contract);
            $movement->unsetRelation('contract');

            return $movement->load(['contract.client', 'items.contractItem.product']);
        });
    }
}
