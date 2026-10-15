<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAudit extends Model
{
    protected $fillable = [
        'company_id', 'user_id', 'entity_type', 'entity_id', 'operation',
        'source', 'module', 'user_snapshot', 'before_state', 'after_state', 'reason',
    ];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'user_snapshot' => 'array'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
