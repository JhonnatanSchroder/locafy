<?php

namespace App\Http\Resources;

use App\Enums\ClientType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => match ($this->type) {
                ClientType::Individual => 'Pessoa Física',
                ClientType::Company => 'Pessoa Jurídica',
            },
            'name' => $this->name,
            'document' => $this->document,
            'phone' => $this->phone,
            'residential_address' => $this->residential_address,
            'notes' => $this->notes,
        ];
    }
}
