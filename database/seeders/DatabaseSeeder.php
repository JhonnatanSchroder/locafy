<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::query()->firstOrCreate([
            'name' => 'Locafy Demo',
        ]);

        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'company_id' => $company->id,
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::Admin,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'jhonnatangustavo012@gmail.com'],
            [
                'company_id' => $company->id,
                'name' => 'Jhonnatan Gustavo',
                'password' => '12345678',
                'email_verified_at' => now(),
                'role' => UserRole::Admin,
            ],
        );

        $defaultProducts = [
            [
                'name' => 'Andaime',
                'type' => ProductType::Quantity,
                'default_price' => 0.60,
                'unit' => 'peça',
                'stock_total' => 0,
                'active' => true,
            ],
            [
                'name' => 'Rodinha',
                'type' => ProductType::Quantity,
                'default_price' => 1.00,
                'unit' => 'unidade',
                'stock_total' => 0,
                'active' => true,
            ],
            [
                'name' => 'Tábua',
                'type' => ProductType::Quantity,
                'default_price' => 1.00,
                'unit' => 'unidade',
                'stock_total' => 0,
                'active' => true,
            ],
            [
                'name' => 'Betoneira',
                'type' => ProductType::Individual,
                'default_price' => null,
                'unit' => 'máquina',
                'stock_total' => null,
                'active' => true,
            ],
        ];

        foreach ($defaultProducts as $product) {
            Product::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $product['name'],
                ],
                $product,
            );
        }
    }
}
