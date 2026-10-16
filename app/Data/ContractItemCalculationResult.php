<?php

namespace App\Data;

use Illuminate\Support\Collection;

class ContractItemCalculationResult
{
    /** @param Collection<int, ContractRentalPeriod> $periods */
    public function __construct(
        public int $contractItemId,
        public int $currentQuantity,
        public int $billableQuantityDays,
        public ?string $subtotal,
        public bool $supported,
        public Collection $periods = new Collection,
    ) {}
}
