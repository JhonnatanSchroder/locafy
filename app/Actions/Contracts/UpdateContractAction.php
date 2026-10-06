<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\ContractLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateContractAction
{
    /**
     * @param  array{
     *     client_id: int,
     *     status: string,
     *     worksite_address?: string|null,
     *     started_at: string,
     *     ended_at?: string|null,
     *     charge_saturdays: bool,
     *     next_charge_date?: string|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         id?: int|null,
     *         product_id: int,
     *         billing_period: string,
     *         unit_price: numeric-string|float|int
     *     }>
     * }  $data
     */
    public function handle(Contract $contract, array $data): Contract
    {
        return DB::transaction(function () use ($contract, $data): Contract {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            if (in_array($contract->status, [ContractStatus::Finalized, ContractStatus::Cancelled])) {
                throw ValidationException::withMessages(['status' => 'Contrato encerrado. Alterações operacionais não são permitidas.']);
            }
            $finalize = $data['status'] === ContractStatus::Finalized->value;
            if ($finalize) {
                $data['status'] = $contract->status->value;
            }
            if ($contract->status === ContractStatus::Returned && $contract->ended_at !== null) {
                $data['ended_at'] = $contract->ended_at;
            }
            $items = $data['items'];
            unset($data['items']);

            $contract->update($data);

            $existingItems = $contract->items()
                ->get()
                ->keyBy('id');

            $keptItemIds = [];

            foreach ($items as $item) {
                $itemId = $item['id'] ?? null;

                unset($item['id']);

                if ($itemId === null) {
                    $keptItemIds[] = $contract->items()
                        ->create($item)
                        ->id;

                    continue;
                }

                $contractItem = $existingItems->get((int) $itemId);

                if ($contractItem === null) {
                    throw ValidationException::withMessages([
                        'items' => 'Um dos itens informados não pertence a este contrato.',
                    ]);
                }

                $contractItem->update($item);

                $keptItemIds[] = $contractItem->id;
            }

            $removableItemIds = $existingItems
                ->keys()
                ->diff($keptItemIds)
                ->values();

            if (
                $contract->items()
                    ->whereIn('id', $removableItemIds)
                    ->whereHas('movementItems')
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Não é possível remover itens que já possuem movimentação física.',
                ]);
            }

            $contract->items()
                ->whereIn('id', $removableItemIds)
                ->delete();

            $contract->unsetRelations();
            if ($finalize) {
                $contract = app(FinalizeContractAction::class)->handle($contract);
            } elseif ($contract->status !== ContractStatus::Cancelled) {
                $contract = app(ContractLifecycleService::class)->synchronize($contract);
            }

            return $contract->load([
                'client',
                'items.product',
                'freights',
            ]);
        });
    }
}
