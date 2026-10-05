<?php

namespace App\Services;

use App\Data\ContractCalculationResult;
use App\Models\Contract;
use App\Models\Freight;

class ContractAccrualService
{
    public function __construct(private ContractCalculationService $calculator) {}

    /**
     * @return array{calculation: ContractCalculationResult, freight_count: int, freight_total: string, total_accrued: string|null}
     */
    public function summarize(Contract $contract): array
    {
        $contract->loadMissing('freights');

        $calculation = $this->calculator->calculate($contract);
        $freightCount = 0;
        $freightTotalCents = 0;

        foreach ($contract->freights as $freight) {
            if ($freight instanceof Freight) {
                $freightCount += $freight->quantity;
                $freightTotalCents += $freight->quantity * $this->decimalToCents((string) $freight->unit_amount);
            }
        }

        return [
            'calculation' => $calculation,
            'freight_count' => $freightCount,
            'freight_total' => $this->formatCents($freightTotalCents),
            'total_accrued' => $calculation->rentalTotal === null
                ? null
                : $this->formatCents($this->decimalToCents($calculation->rentalTotal) + $freightTotalCents),
        ];
    }

    private function decimalToCents(string $amount): int
    {
        $normalized = str_contains($amount, '.')
            ? $amount
            : "{$amount}.00";

        [$reais, $cents] = explode('.', $normalized, 2);
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
