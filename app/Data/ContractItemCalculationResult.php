<?php

namespace App\Data;

class ContractItemCalculationResult
{
    public function __construct(
        public int $contractItemId,
        public int $currentQuantity,
        public int $billableQuantityDays,
        public ?string $subtotal,
        public bool $supported,
    ) {}
}
