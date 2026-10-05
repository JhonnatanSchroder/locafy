<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Movement;
use App\Models\MovementItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovementItem>
 */
class MovementItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();
        $contract = Contract::factory()->for($company);

        return [
            'movement_id' => Movement::factory()->for($company)->for($contract),
            'contract_item_id' => ContractItem::factory()->for($contract),
            'quantity' => fake()->numberBetween(1, 20),
            'equipment_id' => null,
        ];
    }
}
