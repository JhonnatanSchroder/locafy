<?php

namespace App\Data;

use Illuminate\Support\Collection;

class ContractCalculationResult
{
    /**
     * @param  Collection<int, ContractItemCalculationResult>  $items
     */
    public function __construct(
        public string $calculatedUntil,
        public ?string $rentalTotal,
        public bool $calculationComplete,
        public Collection $items,
    ) {}

    public function item(int $contractItemId): ?ContractItemCalculationResult
    {
        return $this->items->first(
            fn (ContractItemCalculationResult $item): bool => $item->contractItemId === $contractItemId
        );
    }
}
