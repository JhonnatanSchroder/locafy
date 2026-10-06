<?php

namespace App\Http\Requests\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

class StoreContractAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');

        return $contract instanceof Contract
            && $this->user()?->company_id === $contract->company_id
            && ! in_array($contract->status, [ContractStatus::Finalized, ContractStatus::Cancelled], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ];
    }
}
