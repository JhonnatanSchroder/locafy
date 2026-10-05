<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Movement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movement>
 */
class MovementFactory extends Factory
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
            'type' => MovementType::Withdrawal,
            'occurred_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
