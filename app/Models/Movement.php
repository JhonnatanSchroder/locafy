<?php

namespace App\Models;

use App\Enums\MovementType;
use Database\Factories\MovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $company_id
 * @property int $contract_id
 * @property MovementType $type
 * @property Carbon $occurred_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['contract_id', 'type', 'occurred_at', 'notes'])]
class Movement extends Model
{
    /** @use HasFactory<MovementFactory> */
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

    /**
     * @return HasMany<MovementItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MovementItem::class);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (Movement $movement): void {
            $movement->validateCompanyContract();
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
            'type' => MovementType::class,
            'occurred_at' => 'datetime',
        ];
    }

    private function validateCompanyContract(): void
    {
        $contract = $this->contract()->first();

        if ($contract === null) {
            return;
        }

        if ($this->company_id !== $contract->company_id) {
            throw ValidationException::withMessages([
                'company_id' => 'A movimentação deve pertencer à mesma empresa do contrato.',
            ]);
        }
    }
}
