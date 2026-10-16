<?php

namespace App\Services;

use App\Data\ContractCalculationResult;

class ContractRentalBreakdownService
{
    /** @return array{calculated_until: string, periods: array<int, array<string, mixed>>, rental_total: string|null} */
    public function fromCalculation(ContractCalculationResult $calculation): array
    {
        return [
            'calculated_until' => $calculation->calculatedUntil,
            'periods' => $calculation->items
                ->flatMap(fn ($item) => $item->periods->map(fn ($period): array => [
                    'contract_item_id' => $period->contractItemId,
                    'product' => ['name' => $period->productName],
                    'started_at' => $period->startedAt,
                    'ended_at' => $period->endedAt,
                    'quantity' => $period->quantity,
                    'billable_days' => $period->billableDays,
                    'unit_price' => $period->unitPrice,
                    'subtotal' => $period->subtotal,
                    'changes' => $period->changes,
                ]))
                ->values()
                ->all(),
            'rental_total' => $calculation->rentalTotal,
        ];
    }
}
