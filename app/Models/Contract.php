<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $charge_interval_days
 * @property Collection<int, Payment> $payments
 * @property Collection<int, ContractAttachment> $attachments
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property ContractStatus $status
 * @property string|null $worksite_address
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property bool $charge_saturdays
 * @property Carbon|null $next_charge_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['client_id', 'status', 'worksite_address', 'started_at', 'ended_at', 'charge_saturdays', 'next_charge_date', 'charge_interval_days', 'notes'])]
class Contract extends Model
{
    public const DEFAULT_CHARGE_INTERVAL_DAYS = 15;

    protected $attributes = ['charge_interval_days' => 15];

    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<ContractItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ContractItem::class);
    }

    /**
     * @return HasMany<Movement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Charge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /** @return HasMany<Freight, $this> */
    public function freights(): HasMany
    {
        return $this->hasMany(Freight::class);
    }

    /** @return HasMany<ContractAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ContractAttachment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'charge_saturdays' => 'boolean',
            'next_charge_date' => 'date',
            'charge_interval_days' => 'integer',
        ];
    }
}
