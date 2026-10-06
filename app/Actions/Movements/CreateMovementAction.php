<?php

namespace App\Actions\Movements;

use App\Models\Contract;
use App\Models\Movement;
use App\Services\ContractLifecycleService;
use App\Services\MovementTimelineValidator;
use Illuminate\Support\Facades\DB;

class CreateMovementAction
{
    public function __construct(private MovementTimelineValidator $timeline) {}

    /**
     * @param  array{type: string, occurred_at: string, notes?: string|null, items: array<int, array{contract_item_id: int, quantity: int, equipment_id?: int|null}>}  $data
     */
    public function handle(Contract $contract, array $data): Movement
    {
        $items = collect($data['items'])
            ->filter(fn (array $item): bool => (int) $item['quantity'] > 0)
            ->values()
            ->all();

        return DB::transaction(function () use ($contract, $data, $items): Movement {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $this->timeline->validateMovementPayload($contract, $data['type'], $data['occurred_at'], $items);

            $movement = $contract->company->movements()->create([
                'contract_id' => $contract->id,
                'type' => $data['type'],
                'occurred_at' => $data['occurred_at'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $movement->items()->create([
                    'contract_item_id' => $item['contract_item_id'],
                    'quantity' => $item['quantity'],
                    'equipment_id' => $item['equipment_id'] ?? null,
                ]);
            }

            app(ContractLifecycleService::class)->synchronize($contract);

            return $movement->load(['contract.client', 'items.contractItem.product']);
        });
    }
}
