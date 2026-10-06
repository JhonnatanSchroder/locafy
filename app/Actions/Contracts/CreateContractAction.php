<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractItem;
use Illuminate\Support\Facades\DB;

class CreateContractAction
{
    /**
     * @param  array{client_id: int, worksite_address?: string|null, started_at: string, ended_at?: string|null, charge_saturdays: bool, next_charge_date?: string|null, notes?: string|null, items: array<int, array{product_id: int, billing_period: string, unit_price: numeric-string|float|int, initial_quantity?: int|null}>, initial_freight?: array{quantity?: int|null, unit_amount?: numeric-string|float|int|null, notes?: string|null}|null}  $data
     */
    public function handle(Company $company, array $data): Contract
    {
        return DB::transaction(function () use ($company, $data): Contract {
            $items = $data['items'];
            unset($data['items']);

            $initialFreight = $data['initial_freight'] ?? null;
            unset($data['initial_freight']);

            $data['charge_interval_days'] ??= Contract::DEFAULT_CHARGE_INTERVAL_DAYS;

            $contract = $company->contracts()->create([
                ...$data,
                'status' => ContractStatus::Active,
            ]);

            $initialMovementItems = [];

            foreach ($items as $item) {
                $initialQuantity = (int) ($item['initial_quantity'] ?? 0);
                unset($item['initial_quantity']);

                /** @var ContractItem $contractItem */
                $contractItem = $contract->items()->create($item);
                $contractItem->load('product');

                if ($contractItem->product->type === ProductType::Quantity && $initialQuantity > 0) {
                    $initialMovementItems[] = [
                        'contract_item_id' => $contractItem->id,
                        'quantity' => $initialQuantity,
                        'equipment_id' => null,
                    ];
                }
            }

            if ($initialMovementItems !== []) {
                $movement = $company->movements()->create([
                    'contract_id' => $contract->id,
                    'type' => MovementType::Withdrawal,
                    'occurred_at' => $contract->started_at,
                    'notes' => null,
                ]);

                foreach ($initialMovementItems as $movementItem) {
                    $movement->items()->create($movementItem);
                }
            }

            $initialFreightQuantity = is_array($initialFreight) ? (int) ($initialFreight['quantity'] ?? 0) : 0;

            if (is_array($initialFreight) && $initialFreightQuantity > 0 && isset($initialFreight['unit_amount'])) {
                $contract->freights()->create([
                    'company_id' => $company->id,
                    'quantity' => $initialFreightQuantity,
                    'unit_amount' => $initialFreight['unit_amount'],
                    'occurred_at' => $contract->started_at,
                    'notes' => $initialFreight['notes'] ?? null,
                ]);
            }

            return $contract->load(['client', 'items.product', 'movements.items', 'freights']);
        });
    }
}
