<?php

namespace App\Actions\Clients;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RestoreClientAction
{
    public function handle(Client $client, User $user, RecordAdminAuditAction $audit): Client
    {
        return DB::transaction(function () use ($client, $user, $audit): Client {
            $model = Client::withTrashed()->lockForUpdate()->findOrFail($client->id);
            $before = ['status' => $model->status?->value ?? 'ACTIVE', 'deleted_at' => $model->deleted_at?->toISOString()];
            $model->restore();
            $model->forceFill(['status' => ClientStatus::Inactive, 'status_changed_at' => now(), 'status_changed_by' => $user->id])->save();
            $audit->handle($user, 'client', $model->id, 'restored', $before, ['status' => ClientStatus::Inactive->value, 'deleted_at' => null]);

            return $model->refresh();
        });
    }
}
