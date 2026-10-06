<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $company_id
 * @property int $contract_id
 * @property string $rental_amount
 * @property string $freight_amount
 * @property string $total_amount
 * @property Carbon|null $cycle_due_date
 * @property Carbon $due_date
 * @property Carbon $calculated_until
 * @property string $status
 * @property string|null $notes
 * @property Contract $contract
 * @property Collection<int, Payment> $payments
 */
class Charge extends Model
{
    protected $fillable = ['company_id', 'contract_id', 'rental_amount', 'freight_amount', 'total_amount', 'due_date', 'cycle_due_date', 'calculated_until', 'status', 'notes'];

    protected static function booted(): void
    {
        static::saving(function (Charge $model): void {
            if ($model->exists && $model->isDirty(['company_id', 'contract_id', 'rental_amount', 'freight_amount', 'total_amount', 'due_date', 'cycle_due_date', 'calculated_until'])) {
                throw ValidationException::withMessages(['charge' => 'Financial snapshots are immutable.']);
            }
            $parent = $model->contract()->first();
            if ($parent === null || $parent->company_id !== $model->company_id) {
                throw ValidationException::withMessages(['company_id' => 'Financial record must belong to the same company.']);
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rental_amount' => 'decimal:2', 'freight_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'due_date' => 'date', 'cycle_due_date' => 'date', 'calculated_until' => 'date'];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
