<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreContractAttachmentsAction
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, ContractAttachment>
     */
    public function handle(Contract $contract, array $files, User $user): Collection
    {
        $contract->loadCount('attachments');

        if ($contract->attachments_count + count($files) > 10) {
            throw ValidationException::withMessages([
                'attachments' => 'O contrato pode ter no máximo 10 fotos.',
            ]);
        }

        $disk = $this->disk();
        $stored = collect();

        foreach ($files as $file) {
            $extension = match ($file->getMimeType()) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => $file->extension(),
            };
            $path = $file->storeAs(
                "contracts/{$contract->company_id}/{$contract->id}",
                Str::uuid()->toString().'.'.$extension,
                $disk
            );

            if ($path === false) {
                throw ValidationException::withMessages(['attachments' => 'Não foi possível salvar uma das fotos.']);
            }

            $stored->push($contract->attachments()->create([
                'company_id' => $contract->company_id,
                'uploaded_by' => $user->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]));
        }

        return $stored;
    }

    private function disk(): string
    {
        return (string) config('filesystems.contract_attachments_disk', config('filesystems.default'));
    }
}
