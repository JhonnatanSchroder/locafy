<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company_id !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['contract_id' => ['required', 'integer', Rule::exists('contracts', 'id')->where('company_id', $this->user()?->company_id)], 'due_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
