<?php

namespace App\Http\Requests\Contracts;

use App\Enums\BillingPeriod;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Models\Contract;
use App\Models\Product;
use App\Services\MovementBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->company_id !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'charge_saturdays' => $this->boolean('charge_saturdays'),
        ]);
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
            'status' => ['required', Rule::enum(ContractStatus::class)],
            'worksite_address' => ['nullable', 'string', 'max:5000'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date'],
            'charge_saturdays' => ['boolean'],
            'next_charge_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'initial_freight' => ['nullable', 'array'],
            'initial_freight.quantity' => ['nullable', 'integer', 'min:0'],
            'initial_freight.unit_amount' => ['nullable', 'numeric', 'gt:0'],
            'initial_freight.notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'items.*.billing_period' => ['required', Rule::enum(BillingPeriod::class)],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
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
                $this->validateContractItemIds($validator);
                $this->validateBillingPeriods($validator);
                $this->validateEndedAt($validator);
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
            $validator->errors()->add('initial_freight.unit_amount', 'Informe o valor unitário do frete inicial.');
        }
    }

    private function validateEndedAt(Validator $validator): void
    {
        $endedAt = $this->input('ended_at');

        if ($endedAt === null || $endedAt === '') {
            return;
        }

        $contract = $this->route('contract');

        if (! $contract instanceof Contract) {
            return;
        }

        $endedAtCarbon = CarbonImmutable::parse($endedAt);
        $startedAtCarbon = CarbonImmutable::parse($this->input('started_at'));

        if ($endedAtCarbon->lt($startedAtCarbon)) {
            $validator->errors()->add('ended_at', 'O fim do contrato não pode ser anterior ao início.');
        }

        if ($endedAtCarbon->gt(CarbonImmutable::now())) {
            $validator->errors()->add('ended_at', 'O fim do contrato não pode estar no futuro.');
        }

        $lastMovement = $contract->movements()->latest('occurred_at')->first();

        if ($lastMovement !== null && $endedAtCarbon->lt($lastMovement->occurred_at)) {
            $validator->errors()->add('ended_at', 'O fim do contrato não pode ser anterior à última movimentação.');
        }

        $contract->loadMissing(['items.product', 'items.movementItems.movement']);
        $balances = app(MovementBalanceService::class)->currentQuantities($contract);

        $hasQuantityItemsOut = $contract->items->contains(
            fn ($item): bool => $item->product->type === ProductType::Quantity
                && (int) ($balances->get($item->id) ?? 0) > 0
        );

        if ($hasQuantityItemsOut) {
            $validator->errors()->add('ended_at', 'Não é possível encerrar a locação enquanto houver itens não devolvidos.');
        }
    }

    private function validateContractItemIds(Validator $validator): void
    {
        $contract = $this->route('contract');

        if (! $contract instanceof Contract) {
            return;
        }

        $itemIds = $contract->items()->pluck('id')->all();

        foreach ($this->input('items', []) as $index => $item) {
            $itemId = $item['id'] ?? null;

            if ($itemId !== null && ! in_array((int) $itemId, $itemIds, true)) {
                $validator->errors()->add(
                    "items.{$index}.id",
                    'O item informado não pertence a este contrato.'
                );
            }
        }
    }

    private function validateBillingPeriods(Validator $validator): void
    {
        $productIds = collect($this->input('items', []))
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
                    'Produtos por quantidade aceitam apenas cobrança diária.'
                );
            }
        }
    }
}
