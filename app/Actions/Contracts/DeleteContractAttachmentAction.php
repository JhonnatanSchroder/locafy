<?php

namespace App\Actions\Contracts;

use App\Models\ContractAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteContractAttachmentAction
{
    public function handle(ContractAttachment $attachment): void
    {
        $disk = (string) config('filesystems.contract_attachments_disk', config('filesystems.default'));

        if (! Storage::disk($disk)->delete($attachment->file_path)) {
            throw ValidationException::withMessages(['attachment' => 'Não foi possível remover o arquivo da foto.']);
        }

        $attachment->delete();
    }
}
