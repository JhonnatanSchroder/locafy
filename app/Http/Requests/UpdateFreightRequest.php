<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFreightRequest extends FormRequest
{
    /** @return ($key is null ? array{quantity: int, unit_amount: numeric-string, notes?: string|null} : mixed) */
    public function validated($key = null, $default = null)
    {
        return parent::validated($key, $default);
    }

    public function authorize(): bool
    {
        return $this->user()?->company_id !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'unit_amount' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                'gt:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
