<?php

namespace Database\Factories;

use App\Enums\EquipmentStatus;
use App\Models\Company;
use App\Models\Equipment;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
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
            'product_id' => fn (array $attributes) => Product::factory()
                ->for(Company::query()->findOrFail($attributes['company_id']))
                ->individual(),
            'name' => fake()->words(2, true),
            'brand' => fake()->optional()->company(),
            'notes' => fake()->optional()->sentence(),
            'status' => EquipmentStatus::Available,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this
            ->afterMaking(function (Equipment $equipment): void {
                $this->syncCompanyWithProduct($equipment);
            })
            ->afterCreating(function (Equipment $equipment): void {
                $this->syncCompanyWithProduct($equipment);
                $equipment->saveQuietly();
            });
    }

    /**
     * Indicate that the equipment is rented.
     */
    public function rented(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EquipmentStatus::Rented,
        ]);
    }

    /**
     * Indicate that the equipment is under maintenance.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EquipmentStatus::Maintenance,
        ]);
    }

    /**
     * Indicate that the equipment is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EquipmentStatus::Inactive,
        ]);
    }

    private function syncCompanyWithProduct(Equipment $equipment): void
    {
        if ($equipment->product_id === null) {
            return;
        }

        $product = Product::query()->find($equipment->product_id);

        if ($product === null) {
            return;
        }

        $equipment->company_id = $product->company_id;
    }
}
