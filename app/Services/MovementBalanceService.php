<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Contract;
use App\Models\ContractItem;
use Illuminate\Support\Collection;

class MovementBalanceService
{
    /**
     * @return Collection<int, int>
     */
    public function currentQuantities(Contract $contract): Collection
    {
        $contract->loadMissing(['items.movementItems.movement']);

        return $contract->items
            ->mapWithKeys(fn (ContractItem $item): array => [
                $item->id => $this->currentQuantityFromLoadedItem($item),
            ]);
    }

    public function currentQuantity(ContractItem $item): int
    {
        $item->loadMissing('movementItems.movement');

        return $this->currentQuantityFromLoadedItem($item);
    }

    private function currentQuantityFromLoadedItem(ContractItem $item): int
    {
        return $item->movementItems->sum(function ($movementItem): int {
            return match ($movementItem->movement->type) {
                MovementType::Withdrawal => $movementItem->quantity,
                MovementType::Return => -$movementItem->quantity,
            };
        });
    }
}
