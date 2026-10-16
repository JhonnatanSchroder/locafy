<?php

namespace App\Data;

class ContractRentalPeriod
{
    /** @param array<int, array{movement_id: int, type: string, occurred_at: string, quantity_change: int}> $changes */
    public function __construct(
        public int $contractItemId,
        public string $productName,
        public string $startedAt,
        public string $endedAt,
        public int $quantity,
        public int $billableDays,
        public string $unitPrice,
        public string $subtotal,
        public array $changes = [],
    ) {}
}
