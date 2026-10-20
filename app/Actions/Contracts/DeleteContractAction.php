<?php

namespace App\Actions\Contracts;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\Contract;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteContractAction
{
    public function handle(Contract $contract, User $user, RecordAdminAuditAction $audit): void
    {
        abort_unless($user->canAdministrateCompany(), 403);

        DB::transaction(function () use ($contract, $user, $audit): void {
            $contract = Contract::query()
                ->where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($contract->id);

            $movements = $contract->movements()->lockForUpdate()->get();
            $payments = $contract->payments()->lockForUpdate()->get();
            $charges = $contract->charges()->lockForUpdate()->get();
            $freights = $contract->freights()->lockForUpdate()->get();
            $attachments = $contract->attachments()->withTrashed()->lockForUpdate()->get();
            $items = $contract->items()->lockForUpdate()->get();
            $attachmentPaths = $attachments->pluck('file_path')->filter()->values()->all();
            $paymentAmount = $payments->sum(fn ($payment): int => Money::cents((string) $payment->amount));
            $discountAmount = $payments->sum(fn ($payment): int => Money::cents((string) ($payment->discount_amount ?? '0.00')));
            $freightAmount = $freights->sum(fn ($freight): int => Money::cents((string) $freight->unit_amount) * $freight->quantity);

            $before = [
                'contract_number' => $contract->id,
                'status' => $contract->status?->value ?? $contract->status,
                'movement_count' => $movements->count(),
                'payment_count' => $payments->count(),
                'charge_count' => $charges->count(),
                'freight_count' => $freights->count(),
                'attachment_count' => $attachments->count(),
            ];

            $payments->each->delete();
            $charges->each->delete();
            $freights->each->delete();
            $movements->each->delete();
            $attachments->each->forceDeleteQuietly();
            $items->each->delete();

            $audit->handle($user, 'contract', $contract->id, 'deleted', $before, [
                'deleted' => true,
                'payment_amount' => Money::format($paymentAmount),
                'discount_amount' => Money::format($discountAmount),
                'freight_amount' => Money::format($freightAmount),
                'movement_count' => $movements->count(),
                'payment_count' => $payments->count(),
                'charge_count' => $charges->count(),
                'freight_count' => $freights->count(),
                'attachment_count' => $attachments->count(),
                'contract_item_count' => $items->count(),
            ]);
            $contract->delete();

            DB::afterCommit(function () use ($attachmentPaths): void {
                if ($attachmentPaths === []) {
                    return;
                }

                $disk = (string) config('filesystems.contract_attachments_disk', config('filesystems.default'));
                try {
                    if (! Storage::disk($disk)->delete($attachmentPaths)) {
                        Log::warning('Some contract attachments could not be removed after contract deletion.', [
                            'disk' => $disk,
                            'paths' => $attachmentPaths,
                        ]);
                    }
                } catch (\Throwable $exception) {
                    Log::error('Contract attachment cleanup failed after contract deletion.', [
                        'disk' => $disk,
                        'paths' => $attachmentPaths,
                        'exception' => $exception,
                    ]);
                }
            });
        });
    }
}
