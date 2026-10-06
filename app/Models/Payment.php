<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $contract_id
 * @property int|null $charge_id
 * @property string $amount
 * @property string $discount_amount
 * @property Carbon $paid_at
 * @property string $method
 * @property string|null $notes
 * @property Contract|null $contract
 * @property Charge|null $charge
 */
class Payment extends Model
{
    protected $fillable = ['company_id', 'contract_id', 'charge_id', 'amount', 'discount_amount', 'paid_at', 'method', 'notes'];

    protected static function booted(): void
    {
        static::saving(function (Payment $model): void {
            $charge = $model->charge_id === null ? null : $model->charge()->first();
            if ($charge !== null && $model->contract_id === null) {
                $model->contract_id = $charge->contract_id;
            }
            $contract = $model->contract()->first();
            if ($contract === null || $contract->company_id !== $model->company_id || ($model->charge_id !== null && ($charge === null || $charge->company_id !== $model->company_id || $charge->contract_id !== $model->contract_id))) {
                throw ValidationException::withMessages(['company_id' => 'Financial record must belong to the same company and contract.']);
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function settledAmount(): string
    {
        return Money::format(
            Money::cents($this->amount) + Money::cents($this->discount_amount)
        );
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<Charge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
