<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Contract;
use App\Models\Movement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MovementTimelineValidator
{
    /**
     * @param  array<int, array{contract_item_id: int, quantity: int, equipment_id?: int|null}>  $items
     */
    public function validateMovementPayload(Contract $contract, string $type, string $occurredAt, array $items, ?Movement $ignoreMovement = null): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Informe ao menos um item com quantidade maior que zero.',
            ]);
        }

        $occurredAtCarbon = CarbonImmutable::parse($occurredAt, ContractCalculationService::TIMEZONE);
        $now = CarbonImmutable::now(ContractCalculationService::TIMEZONE);

        if ($occurredAtCarbon->lt($contract->started_at)) {
            throw ValidationException::withMessages([
                'occurred_at' => 'A movimentação não pode ocorrer antes do início do contrato.',
            ]);
        }

        if ($occurredAtCarbon->gt($now)) {
            throw ValidationException::withMessages([
                'occurred_at' => 'A movimentação não pode ocorrer no futuro.',
            ]);
        }

        if ($contract->ended_at !== null && $occurredAtCarbon->gt($contract->ended_at)) {
            throw ValidationException::withMessages([
                'occurred_at' => 'A movimentação não pode ocorrer após o fim do contrato.',
            ]);
        }

        $contractItemIds = $contract->items()->pluck('id')->all();

        foreach ($items as $index => $item) {
            if (! in_array((int) $item['contract_item_id'], $contractItemIds, true)) {
                throw ValidationException::withMessages([
                    "items.{$index}.contract_item_id" => 'O item informado não pertence a este contrato.',
                ]);
            }

            if ((int) $item['quantity'] <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'A quantidade deve ser maior que zero.',
                ]);
            }
        }

        $this->validateTimeline($contract, [
            'id' => $ignoreMovement?->id ?? 0,
            'type' => $type,
            'occurred_at' => $occurredAtCarbon,
            'items' => collect($items)->map(fn (array $item): array => [
                'contract_item_id' => (int) $item['contract_item_id'],
                'quantity' => (int) $item['quantity'],
            ]),
        ], $ignoreMovement);
    }

    /**
     * @param  array{id: int, type: string, occurred_at: CarbonImmutable, items: Collection<int, array{contract_item_id: int, quantity: int}>}  $candidate
     */
    private function validateTimeline(Contract $contract, array $candidate, ?Movement $ignoreMovement): void
    {
        $contract->loadMissing(['movements.items']);

        $movements = $contract->movements
            ->reject(fn (Movement $movement): bool => $ignoreMovement !== null && $movement->id === $ignoreMovement->id)
            ->map(fn (Movement $movement): array => [
                'id' => $movement->id,
                'type' => $movement->type->value,
                'occurred_at' => CarbonImmutable::parse($movement->occurred_at),
                'items' => $movement->items->map(fn ($item): array => [
                    'contract_item_id' => $item->contract_item_id,
                    'quantity' => $item->quantity,
                ]),
            ])
            ->push($candidate)
            ->sortBy([
                ['occurred_at', 'asc'],
                ['id', 'asc'],
            ]);

        $balances = [];

        foreach ($movements as $movement) {
            foreach ($movement['items'] as $item) {
                $contractItemId = $item['contract_item_id'];
                $balances[$contractItemId] ??= 0;

                $balances[$contractItemId] += $movement['type'] === MovementType::Withdrawal->value
                    ? $item['quantity']
                    : -$item['quantity'];

                if ($balances[$contractItemId] < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'A movimentação deixaria o histórico físico com saldo negativo.',
                    ]);
                }
            }
        }
    }
}
