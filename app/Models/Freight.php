<?php

namespace App\Models;

use Database\Factories\FreightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $company_id
 * @property int $contract_id
 * @property int $quantity
 * @property string $unit_amount
 * @property Carbon $occurred_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'contract_id', 'quantity', 'unit_amount', 'occurred_at', 'notes'])]
class Freight extends Model
{
    /** @use HasFactory<FreightFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Freight $freight): void {
            $freight->validateQuantity();
            $freight->validateCompanyContract();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    private function validateQuantity(): void
    {
        if ($this->quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'A quantidade de fretes deve ser maior que zero.',
            ]);
        }
    }

    private function validateCompanyContract(): void
    {
        $contract = $this->contract()->first();

        if ($contract === null) {
            return;
        }

        if ($this->company_id !== $contract->company_id) {
            throw ValidationException::withMessages([
                'company_id' => 'O frete deve pertencer à mesma empresa do contrato.',
            ]);
        }
    }
}
