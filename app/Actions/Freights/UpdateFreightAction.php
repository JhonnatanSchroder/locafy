<?php

namespace App\Actions\Freights;

use App\Models\Freight;

class UpdateFreightAction
{
    /**
     * @param array{
     *     quantity: int,
     *     unit_amount: numeric-string|float|int,
     *     notes?: string|null
     * } $data
     */
    public function handle(Freight $freight, array $data): Freight
    {
        $freight->update([
            'quantity' => $data['quantity'],
            'unit_amount' => $data['unit_amount'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $freight->refresh();
    }
}
