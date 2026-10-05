<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
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
            'client_id' => Client::factory()->for($company),
            'status' => ContractStatus::Active,
            'worksite_address' => fake()->streetAddress(),
            'started_at' => fake()->dateTimeBetween('-1 month', '+1 week'),
            'charge_saturdays' => fake()->boolean(),
            'next_charge_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
