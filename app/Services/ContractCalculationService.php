<?php

namespace App\Services;

use App\Data\ContractCalculationResult;
use App\Data\ContractItemCalculationResult;
use App\Enums\BillingPeriod;
use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ContractCalculationService
{
    public const TIMEZONE = 'America/Belem';

    public function __construct(private MovementBalanceService $balances) {}

    public function calculate(Contract $contract): ContractCalculationResult
    {
        $contract->loadMissing(['items.product', 'items.movementItems.movement', 'movements.items']);

        $calculatedUntil = $contract->ended_at !== null
            ? CarbonImmutable::parse($contract->ended_at)->setTimezone(self::TIMEZONE)
            : CarbonImmutable::now(self::TIMEZONE);

        $currentQuantities = $this->balances->currentQuantities($contract);
        $complete = true;
        $totalCents = 0;

        $items = $contract->items->map(function ($item) use ($contract, $calculatedUntil, $currentQuantities, &$complete, &$totalCents): ContractItemCalculationResult {
            $currentQuantity = (int) ($currentQuantities->get($item->id) ?? 0);

            if ($item->product->type !== ProductType::Quantity || $item->billing_period !== BillingPeriod::Day) {
                $complete = false;

                return new ContractItemCalculationResult($item->id, $currentQuantity, 0, null, false);
            }

            $quantityDays = $this->billableQuantityDays($contract, $item->id, $calculatedUntil);
            $subtotalCents = $quantityDays * $this->decimalToCents($item->unit_price);
            $totalCents += $subtotalCents;

            return new ContractItemCalculationResult(
                $item->id,
                $currentQuantity,
                $quantityDays,
                $this->formatCents($subtotalCents),
                true
            );
        });

        return new ContractCalculationResult(
            $calculatedUntil->toDateString(),
            $complete ? $this->formatCents($totalCents) : null,
            $complete,
            $items
        );
    }

    private function billableQuantityDays(Contract $contract, int $contractItemId, CarbonImmutable $calculatedUntil): int
    {
        $start = CarbonImmutable::parse($contract->started_at)->setTimezone(self::TIMEZONE)->startOfDay();
        $end = $calculatedUntil->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        $effectiveChanges = $this->effectiveChanges($contract, $contractItemId);
        $quantity = 0;
        $quantityDays = 0;

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            foreach ($effectiveChanges->get($day->toDateString(), []) as $change) {
                $quantity += $change;
            }

            if ($quantity > 0 && $this->isBillableDay($contract, $day)) {
                $quantityDays += $quantity;
            }
        }

        return $quantityDays;
    }

    /**
     * @return Collection<string, array<int, int>>
     */
    private function effectiveChanges(
        Contract $contract,
        int $contractItemId
    ): Collection {
        $changes = collect();

        foreach ($contract->movements as $movement) {
            $movementAt = CarbonImmutable::parse(
                $movement->occurred_at
            )->setTimezone(self::TIMEZONE);

            // A retirada sempre passa a valer no próprio dia.
            $effectiveDate = $movementAt->toDateString();

            /*
             * A regra de devolução vamos tratar separadamente.
             * Por enquanto mantemos o comportamento atual.
             */
            $isBeforeTen =
                $movementAt->format('H:i:s') < '10:00:00';

            if (
                $movement->type === MovementType::Return
                && ! $isBeforeTen
            ) {
                $effectiveDate = $movementAt
                    ->addDay()
                    ->toDateString();
            }

            foreach ($movement->items as $movementItem) {
                if (
                    $movementItem->contract_item_id
                    !== $contractItemId
                ) {
                    continue;
                }

                $change =
                    $movement->type ===
                    MovementType::Withdrawal
                        ? $movementItem->quantity
                        : -$movementItem->quantity;

                $changes->push([
                    'date' => $effectiveDate,
                    'change' => $change,
                ]);
            }
        }

        return $changes
            ->groupBy('date')
            ->map(
                fn (Collection $dayChanges): array => $dayChanges
                    ->pluck('change')
                    ->all()
            );
    }

    private function isBillableDay(Contract $contract, CarbonImmutable $day): bool
    {
        if ($day->isSunday()) {
            return false;
        }

        if ($day->isSaturday() && ! $contract->charge_saturdays) {
            return false;
        }

        return true;
    }

    private function decimalToCents(string $amount): int
    {
        $normalized = str_contains($amount, '.')
            ? $amount
            : "{$amount}.00";

        [$reais, $cents] = explode('.', $normalized);
        $cents = str_pad(substr($cents, 0, 2), 2, '0');

        return ((int) $reais * 100) + (int) $cents;
    }

    private function formatCents(int $cents): string
    {
        $reais = intdiv($cents, 100);
        $remainingCents = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return "{$reais}.{$remainingCents}";
    }
}
