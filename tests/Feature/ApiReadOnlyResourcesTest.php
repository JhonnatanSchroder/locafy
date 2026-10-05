<?php

use App\Enums\BillingPeriod;
use App\Enums\MovementType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\User;

it('requires authentication for read only api resources', function () {
    $this
        ->getJson('/api/v1/clients')
        ->assertUnauthorized();
});

it('returns forbidden for api users without a company', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/clients')
        ->assertForbidden();
});

it('paginates api listings', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    Client::factory()->count(2)->for($company)->create();
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonPath('meta.current_page', 1);
});

it('only returns clients from the authenticated users company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    Client::factory()->for($company)->create(['name' => 'Visible Client']);
    Client::factory()->for($otherCompany)->create(['name' => 'Hidden Client']);
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Visible Client');
});

it('returns not found for api records from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($otherCompany)->create();
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson("/api/v1/clients/{$client->id}")
        ->assertNotFound();
});

it('returns products and equipments through resources', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create(['name' => 'Betoneira']);
    $equipment = Equipment::factory()->for($company)->for($product)->create(['name' => 'Betoneira 01']);
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Betoneira');

    $this
        ->withToken($token)
        ->getJson("/api/v1/equipments/{$equipment->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Betoneira 01')
        ->assertJsonPath('data.product.name', 'Betoneira');
});

it('returns paginated contracts without nonexistent financial fields', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create(['name' => 'Locafy Cliente']);
    $product = Product::factory()->for($company)->create(['name' => 'Andaime']);
    $contract = Contract::factory()->for($company)->create(['client_id' => $client->id]);
    $contractItem = ContractItem::factory()->for($contract)->for($product)->create([
        'billing_period' => BillingPeriod::Day,
        'unit_price' => '0.60',
    ]);
    $withdrawal = $company->movements()->create([
        'contract_id' => $contract->id,
        'type' => MovementType::Withdrawal,
        'occurred_at' => $contract->started_at,
    ]);
    $withdrawal->items()->create([
        'contract_item_id' => $contractItem->id,
        'quantity' => 12,
        'equipment_id' => null,
    ]);
    $return = $company->movements()->create([
        'contract_id' => $contract->id,
        'type' => MovementType::Return,
        'occurred_at' => $contract->started_at->copy()->addDay(),
    ]);
    $return->items()->create([
        'contract_item_id' => $contractItem->id,
        'quantity' => 3,
        'equipment_id' => null,
    ]);
    $token = $user->createToken('Mobile')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->getJson('/api/v1/contracts');

    $response
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonPath('data.0.number', $contract->id)
        ->assertJsonPath('data.0.client.name', 'Locafy Cliente')
        ->assertJsonPath('data.0.items.0.product.name', 'Andaime')
        ->assertJsonPath('data.0.items.0.current_quantity', 9)
        ->assertJsonMissingPath('data.0.total')
        ->assertJsonMissingPath('data.0.balance')
        ->assertJsonMissingPath('data.0.accumulated')
        ->assertJsonMissingPath('data.0.payments')
        ->assertJsonMissingPath('data.0.discounts')
        ->assertJsonMissingPath('data.0.credits');
});

it('returns not found for api contracts from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $contract = Contract::factory()->for($otherCompany)->create([
        'client_id' => Client::factory()->for($otherCompany)->create()->id,
    ]);
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson("/api/v1/contracts/{$contract->id}")
        ->assertNotFound();
});
