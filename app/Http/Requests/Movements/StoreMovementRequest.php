<?php

namespace App\Http\Requests\Movements;

use App\Enums\MovementType;
use App\Models\Contract;
use App\Models\Movement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMovementRequest extends FormRequest
{
    /** @return ($key is null ? array{contract_id: int, type: string, occurred_at: string, notes?: string|null, items: array<int, array{contract_item_id: int, quantity: int, equipment_id?: int|null}>} : mixed) */
    public function validated($key = null, $default = null)
    {
        return parent::validated($key, $default);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Movement::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('contract') instanceof Contract) {
            $this->merge(['contract_id' => $this->route('contract')->id]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contract_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(MovementType::class)],
            'occurred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array'],
            'items.*.contract_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.equipment_id' => ['nullable', 'integer'],
        ];
    }
}
