<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractItem>
 */
class ContractItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'product_id' => Product::factory(),
            'billing_period' => BillingPeriod::Day,
            'unit_price' => fake()->randomFloat(2, 1, 500),
        ];
    }
}
