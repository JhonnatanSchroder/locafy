<?php

namespace App\Http\Requests\Contracts;

use App\Enums\BillingPeriod;
use App\Enums\ProductType;
use App\Models\Contract;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContractRequest extends FormRequest
{
    /** @return ($key is null ? array{client_id: int, worksite_address?: string|null, started_at: string, charge_saturdays: bool, next_charge_date?: string|null, charge_interval_days?: int, notes?: string|null, items: array<int, array{product_id: int, billing_period: string, unit_price: numeric-string, initial_quantity?: int|null}>, initial_freight?: array{quantity?: int|null, unit_amount?: numeric-string|null, notes?: string|null}|null} : mixed) */
    public function validated($key = null, $default = null)
    {
        return parent::validated($key, $default);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contract::class) ?? false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'charge_interval_days' => Contract::DEFAULT_CHARGE_INTERVAL_DAYS,
        ]);

        $this->merge(['charge_saturdays' => $this->boolean('charge_saturdays')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'worksite_address' => ['nullable', 'string', 'max:5000'],
            'started_at' => ['required', 'date'],
            'charge_saturdays' => ['boolean'],
            'next_charge_date' => ['nullable', 'date'],
            'charge_interval_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'initial_freight' => ['nullable', 'array'],
            'initial_freight.quantity' => ['nullable', 'integer', 'min:0'],
            'initial_freight.unit_amount' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/', 'gt:0'],
            'initial_freight.notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'items.*.billing_period' => ['required', Rule::enum(BillingPeriod::class)],
            'items.*.unit_price' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/', 'min:0'],
            'items.*.initial_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get the after validation callbacks for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateBillingPeriods($validator);
                $this->validateInitialFreight($validator);
            },
        ];
    }

    private function validateInitialFreight(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['initial_freight.quantity', 'initial_freight.unit_amount'])) {
            return;
        }

        $quantity = (int) $this->input('initial_freight.quantity', 0);

        if ($quantity > 0 && blank($this->input('initial_freight.unit_amount'))) {
            $validator->errors()->add('initial_freight.unit_amount', 'Informe o valor unitÃƒÂ¡rio do frete inicial.');
        }
    }

    private function validateBillingPeriods(Validator $validator): void
    {
        $productIds = collect($this->array('items'))
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return;
        }

        $products = Product::query()
            ->where('company_id', $this->user()?->company_id)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($this->input('items', []) as $index => $item) {
            $product = $products->get((int) ($item['product_id'] ?? 0));
            $billingPeriod = $item['billing_period'] ?? null;

            if ($product === null || $billingPeriod === null) {
                continue;
            }

            if ($product->type === ProductType::Quantity && $billingPeriod !== BillingPeriod::Day->value) {
                $validator->errors()->add(
                    "items.{$index}.billing_period",
                    'Produtos por quantidade aceitam apenas cobranÃƒÂ§a diÃƒÂ¡ria.'
                );
            }
        }
    }
}
