<?php

namespace Database\Factories;

use App\Enums\ClientType;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
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
            'type' => fake()->randomElement(ClientType::cases()),
            'name' => fake()->name(),
            'document' => fake()->optional()->numerify('###########'),
            'phone' => fake()->optional()->phoneNumber(),
            'residential_address' => fake()->optional()->address(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
