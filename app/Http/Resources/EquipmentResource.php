<?php

namespace App\Http\Resources;

use App\Enums\EquipmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
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
            'name' => $this->name,
            'brand' => $this->brand,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => match ($this->status) {
                EquipmentStatus::Available => 'Disponível',
                EquipmentStatus::Rented => 'Alugado',
                EquipmentStatus::Maintenance => 'Manutenção',
                EquipmentStatus::Inactive => 'Inativo',
            },
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
