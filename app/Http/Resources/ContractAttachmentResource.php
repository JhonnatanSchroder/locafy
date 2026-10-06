<?php

namespace App\Http\Resources;

use App\Models\ContractAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContractAttachment */
class ContractAttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'created_at' => $this->created_at?->toIso8601String(),
            'uploaded_by' => $this->uploader?->name,
            'view_url' => route('contracts.attachments.show', [$this->contract_id, $this->id]),
            'api_view_url' => route('api.v1.contracts.attachments.show', [$this->contract_id, $this->id]),
        ];
    }
}
