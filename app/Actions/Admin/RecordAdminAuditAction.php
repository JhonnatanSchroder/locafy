<?php

namespace App\Actions\Admin;

use App\Models\AdminAudit;
use App\Models\User;

class RecordAdminAuditAction
{
    public function handle(User $user, string $entityType, int $entityId, string $operation, ?array $before = null, ?array $after = null, ?string $reason = null): AdminAudit
    {
        return AdminAudit::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'operation' => $operation,
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $reason,
        ]);
    }
}
