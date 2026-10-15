<?php

namespace App\Actions\Clients;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteClientAction
{
    public function handle(Client $client, ?User $user = null, ?RecordAdminAuditAction $audit = null): string
    {
        return DB::transaction(function () use ($client, $user, $audit): string {
            $model = Client::withTrashed()->lockForUpdate()->findOrFail($client->id);
            $before = ['status' => $model->status?->value ?? 'ACTIVE', 'deleted_at' => $model->deleted_at?->toISOString()];
            if ($model->contracts()->exists()) {
                $model->delete();
                if ($user && $audit) {
                    $audit->handle($user, 'client', $model->id, 'archived', $before, ['status' => $before['status'], 'deleted_at' => $model->fresh()->deleted_at?->toISOString()]);
                }

                return 'archived';
            }

            if ($user && $audit) {
                $audit->handle($user, 'client', $model->id, 'deleted', $before, null);
            }
            $model->forceDeleteQuietly();

            return 'deleted';
        });
    }
}
