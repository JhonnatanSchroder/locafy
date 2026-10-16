<?php

namespace App\Services;

use App\Data\ContractCalculationResult;
use App\Data\ContractItemCalculationResult;
use App\Data\ContractRentalPeriod;
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

    public function calculate(Contract $contract, ?CarbonImmutable $asOf = null): ContractCalculationResult
    {
        $contract->loadMissing(['items.product', 'items.movementItems.movement', 'movements.items']);

        $calculatedUntil = $contract->ended_at !== null
            ? CarbonImmutable::parse($contract->ended_at)->setTimezone(self::TIMEZONE)
            : CarbonImmutable::now(self::TIMEZONE);
        if ($asOf !== null && $asOf->lt($calculatedUntil)) {
            $calculatedUntil = $asOf->setTimezone(self::TIMEZONE);
        }

        $currentQuantities = $this->balances->currentQuantities($contract);
        $complete = true;
        $totalCents = 0;

        $items = $contract->items->map(function ($item) use ($contract, $calculatedUntil, $currentQuantities, &$complete, &$totalCents): ContractItemCalculationResult {
            $currentQuantity = (int) ($currentQuantities->get($item->id) ?? 0);

            if ($item->product->type !== ProductType::Quantity || $item->billing_period !== BillingPeriod::Day) {
                $complete = false;

                return new ContractItemCalculationResult($item->id, $currentQuantity, 0, null, false);
            }

            $periods = $this->rentalPeriods($contract, $item->id, $item->product->name, $item->unit_price, $calculatedUntil);
            $quantityDays = $periods->sum(fn (ContractRentalPeriod $period): int => $period->billableDays * $period->quantity);
            $subtotalCents = $quantityDays * $this->decimalToCents($item->unit_price);
            $totalCents += $subtotalCents;

            return new ContractItemCalculationResult(
                $item->id,
                $currentQuantity,
                $quantityDays,
                $this->formatCents($subtotalCents),
                true,
                $periods
            );
        });

        return new ContractCalculationResult(
            $calculatedUntil->toDateString(),
            $complete ? $this->formatCents($totalCents) : null,
            $complete,
            $items
        );
    }

    private function rentalPeriods(Contract $contract, int $contractItemId, string $productName, string $unitPrice, CarbonImmutable $calculatedUntil): Collection
    {
        $start = CarbonImmutable::parse($contract->started_at)->setTimezone(self::TIMEZONE)->startOfDay();
        $end = $calculatedUntil->startOfDay();

        if ($end->lt($start)) {
            return collect();
        }

        $effectiveEvents = $this->effectiveEvents($contract, $contractItemId)
            ->filter(fn (array $event): bool => $event['date'] <= $end->toDateString())
            ->groupBy('date');
        $quantity = 0;
        $periods = collect();
        $periodStart = null;
        $periodQuantity = 0;
        $periodChanges = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $dayEvents = $effectiveEvents->get($day->toDateString(), collect());
            if ($dayEvents->isNotEmpty()) {
                $dayChanges = [];
                foreach ($dayEvents as $event) {
                    $quantity += $event['change'];
                    $dayChanges[] = [
                        'movement_id' => $event['movement_id'],
                        'type' => $event['type'],
                        'occurred_at' => $event['occurred_at'],
                        'quantity_change' => $event['change'],
                    ];
                }

                if ($periodStart !== null && $periodQuantity !== $quantity) {
                    $periods->push($this->makePeriod($contractItemId, $productName, $periodStart, $day->subDay(), $periodQuantity, $periodChanges, $unitPrice, $contract));
                    $periodStart = null;
                    $periodChanges = [];
                }

                if ($quantity > 0 && $periodStart === null) {
                    $periodStart = $day;
                    $periodQuantity = $quantity;
                    $periodChanges = $dayChanges;
                } elseif ($quantity === 0) {
                    $periodChanges = [];
                } else {
                    $periodChanges = [...$periodChanges, ...$dayChanges];
                }
            }
        }

        if ($periodStart !== null) {
            $periods->push($this->makePeriod($contractItemId, $productName, $periodStart, $end, $periodQuantity, $periodChanges, $unitPrice, $contract));
        }

        return $periods;
    }

    private function makePeriod(int $contractItemId, string $productName, CarbonImmutable $start, CarbonImmutable $end, int $quantity, array $changes, string $unitPrice, Contract $contract): ContractRentalPeriod
    {
        $billableDays = 0;
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            if ($this->isBillableDay($contract, $day)) {
                $billableDays++;
            }
        }

        return new ContractRentalPeriod($contractItemId, $productName, $start->toDateString(), $end->toDateString(), $quantity, $billableDays, $unitPrice, $this->formatCents($billableDays * $quantity * $this->decimalToCents($unitPrice)), $changes);
    }

    private function effectiveEvents(Contract $contract, int $contractItemId): Collection
    {
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
                    'movement_id' => $movement->id,
                    'type' => $movement->type->value,
                    'occurred_at' => $movementAt->toISOString(),
                ]);
            }
        }

        return $changes;
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
