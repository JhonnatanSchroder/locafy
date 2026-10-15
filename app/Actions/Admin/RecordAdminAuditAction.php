<?php

namespace App\Actions\Admin;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Support\Facades\App;

class RecordAdminAuditAction
{
    public function handle(User $user, string $entityType, int $entityId, string $operation, ?array $before = null, ?array $after = null, ?string $reason = null): AdminAudit
    {
        $request = App::runningInConsole() ? null : request();

        return AdminAudit::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'operation' => $operation,
            'source' => App::runningInConsole() ? 'SYSTEM' : ($request->is('api/*') ? 'API' : 'WEB'),
            'module' => $this->moduleFor($entityType),
            'user_snapshot' => ['name' => $user->name, 'role' => $user->role?->value],
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $reason,
        ]);
    }

    private function moduleFor(string $entityType): string
    {
        return match ($entityType) {
            'client' => 'clients',
            'contract' => 'contracts',
            'movement' => 'movements',
            'payment' => 'payments',
            'freight' => 'freights',
            'product' => 'products',
            'equipment' => 'equipments',
            'user' => 'users',
            'charge' => 'charges',
            default => $entityType,
        };
    }
}
