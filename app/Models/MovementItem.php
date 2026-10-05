<?php

namespace App\Models;

use Database\Factories\MovementItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $movement_id
 * @property int $contract_item_id
 * @property int $quantity
 * @property int|null $equipment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['contract_item_id', 'quantity', 'equipment_id'])]
class MovementItem extends Model
{
    /** @use HasFactory<MovementItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Movement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    /**
     * @return BelongsTo<ContractItem, $this>
     */
    public function contractItem(): BelongsTo
    {
        return $this->belongsTo(ContractItem::class);
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (MovementItem $movementItem): void {
            $movementItem->validateMovementContract();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    private function validateMovementContract(): void
    {
        if ($this->quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'A quantidade da movimentação deve ser maior que zero.',
            ]);
        }

        $movement = $this->movement()->first();
        $contractItem = $this->contractItem()->with('contract')->first();

        if ($movement === null || $contractItem === null) {
            return;
        }

        if ($movement->contract_id !== $contractItem->contract_id) {
            throw ValidationException::withMessages([
                'contract_item_id' => 'O item da movimentação deve pertencer ao mesmo contrato da movimentação.',
            ]);
        }
    }
}
