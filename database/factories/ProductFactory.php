<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'type' => ProductType::Quantity,
            'default_price' => fake()->randomFloat(2, 1, 500),
            'unit' => fake()->randomElement(['peça', 'unidade', 'máquina']),
            'stock_total' => fake()->numberBetween(0, 100),
            'active' => true,
        ];
    }

    /**
     * Indicate that the product is controlled individually.
     */
    public function individual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ProductType::Individual,
            'stock_total' => null,
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => false,
        ]);
    }
}
