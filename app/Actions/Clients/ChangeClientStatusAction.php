<?php

namespace App\Actions\Clients;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChangeClientStatusAction
{
    public function handle(Client $client, ClientStatus $status, User $user, ?string $reason = null, ?RecordAdminAuditAction $audit = null): Client
    {
        return DB::transaction(function () use ($client, $status, $user, $reason, $audit): Client {
            $model = Client::query()->lockForUpdate()->findOrFail($client->id);
            $before = ['status' => $model->status?->value ?? 'ACTIVE', 'status_reason' => $model->status_reason];
            $model->forceFill(['status' => $status, 'status_reason' => $reason, 'status_changed_at' => now(), 'status_changed_by' => $user->id])->save();
            if ($audit) {
                $operation = match ($status) {
                    ClientStatus::Blocked => 'blocked',
                    ClientStatus::Inactive => 'inactivated',
                    ClientStatus::Active => $before['status'] === ClientStatus::Blocked->value ? 'unblocked' : 'reactivated',
                };
                $audit->handle($user, 'client', $model->id, $operation, $before, ['status' => $status->value, 'status_reason' => $reason], $reason);
            }

            return $model->refresh();
        });
    }
}
