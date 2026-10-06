<?php

namespace App\Http\Requests;

use App\Services\ContractCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $value = $this->input('paid_at');
        if (! is_string($value) || $value === '') {
            return;
        }
        try {
            $this->merge(['paid_at' => CarbonImmutable::parse($value, ContractCalculationService::TIMEZONE)->utc()->toIso8601String()]);
        } catch (\Exception) {
            // Leave invalid dates to the existing date validation rule.
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->company_id !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'], 'discount_amount' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'], 'paid_at' => ['required', 'date', 'before_or_equal:now'], 'method' => ['required', Rule::in(['PIX', 'CASH', 'CARD', 'TRANSFER', 'OTHER'])], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
