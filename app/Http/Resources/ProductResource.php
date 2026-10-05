<?php

namespace App\Http\Resources;

use App\Enums\ProductType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'type' => $this->type->value,
            'type_label' => match ($this->type) {
                ProductType::Quantity => 'Quantidade',
                ProductType::Individual => 'Individual',
            },
            'default_price' => $this->default_price,
            'unit' => $this->unit,
            'stock_total' => $this->stock_total,
            'active' => $this->active,
        ];
    }
}
