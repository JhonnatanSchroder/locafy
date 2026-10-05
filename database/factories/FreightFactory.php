<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Freight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Freight>
 */
class FreightFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'contract_id' => Contract::factory()->for($company),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_amount' => fake()->randomFloat(2, 1, 500),
            'occurred_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
