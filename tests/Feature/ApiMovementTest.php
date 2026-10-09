<?php

use App\Enums\BillingPeriod;
use App\Enums\MovementType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Movement;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;

function apiMovementFixture(): array
{
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create([
        'name' => 'João Silva',
        'phone' => '(91) 99999-0000',
        'document' => '12345678900',
    ]);
    $contract = Contract::factory()->for($company)->create([
        'client_id' => $client->id,
        'started_at' => '2026-10-01 08:00:00',
    ]);
    $product = Product::factory()->for($company)->create(['name' => 'Andaime']);
    $contractItem = ContractItem::factory()->for($contract)->for($product)->create([
        'billing_period' => BillingPeriod::Day,
        'unit_price' => '10.00',
    ]);

    return [$company, $user, $client, $contract, $contractItem, $product];
}

function createApiMovement(Company $company, Contract $contract, ContractItem $item, MovementType $type, string $occurredAt, int $quantity = 1): Movement
{
    $movement = $company->movements()->create([
        'contract_id' => $contract->id,
        'type' => $type,
        'occurred_at' => $occurredAt,
    ]);
    $movement->items()->create([
        'contract_item_id' => $item->id,
        'quantity' => $quantity,
        'equipment_id' => null,
    ]);

    return $movement;
}

it('lists only company movements with both types ordered by most recent', function () {
    [$company, $user, $client, $contract, $contractItem, $product] = apiMovementFixture();
    $older = createApiMovement($company, $contract, $contractItem, MovementType::Withdrawal, '2026-10-02 09:00:00', 10);
    $newer = createApiMovement($company, $contract, $contractItem, MovementType::Return, '2026-10-03 09:00:00', 4);
    [$foreignCompany, , , $foreignContract, $foreignItem] = apiMovementFixture();
    createApiMovement($foreignCompany, $foreignContract, $foreignItem, MovementType::Withdrawal, '2026-10-04 09:00:00', 1);
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/movements')
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.type', 'RETURN')
        ->assertJsonPath('data.0.type_label', 'Devolução')
        ->assertJsonPath('data.0.contract.id', $contract->id)
        ->assertJsonPath('data.0.contract.number', $contract->id)
        ->assertJsonPath('data.0.client.id', $client->id)
        ->assertJsonPath('data.0.client.name', 'João Silva')
        ->assertJsonPath('data.0.items.0.contract_item_id', $contractItem->id)
        ->assertJsonPath('data.0.items.0.product.id', $product->id)
        ->assertJsonPath('data.0.items.0.product.name', 'Andaime')
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.1.type', 'WITHDRAWAL');
});

it('filters movement listing by type contract client date range and search', function () {
    [$company, $user, $client, $contract, $contractItem] = apiMovementFixture();
    createApiMovement($company, $contract, $contractItem, MovementType::Withdrawal, '2026-10-02 09:00:00', 10);
    $return = createApiMovement($company, $contract, $contractItem, MovementType::Return, '2026-10-03 09:00:00', 4);
    $token = $user->createToken('Mobile')->plainTextToken;

    $base = "/api/v1/movements?type=RETURN&contract_id={$contract->id}&client_id={$client->id}&date_from=2026-10-03&date_to=2026-10-03";

    $this->withToken($token)->getJson($base)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $return->id);
    $this->withToken($token)->getJson($base.'&search=João')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $return->id);
    $this->withToken($token)->getJson($base.'&search=99999')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $return->id);
    $this->withToken($token)->getJson($base.'&search='.$contract->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $return->id);
});

it('paginates movements with per page limit', function () {
    [$company, $user, , $contract, $contractItem] = apiMovementFixture();
    createApiMovement($company, $contract, $contractItem, MovementType::Withdrawal, '2026-10-02 09:00:00', 1);
    createApiMovement($company, $contract, $contractItem, MovementType::Withdrawal, '2026-10-03 09:00:00', 1);
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/movements?per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2);
});

it('shows a movement with items products and company isolation', function () {
    [$company, $user, , $contract, $contractItem, $product] = apiMovementFixture();
    $movement = createApiMovement($company, $contract, $contractItem, MovementType::Withdrawal, '2026-10-02 09:00:00', 10);
    [$foreignCompany, , , $foreignContract, $foreignItem] = apiMovementFixture();
    $foreignMovement = createApiMovement($foreignCompany, $foreignContract, $foreignItem, MovementType::Withdrawal, '2026-10-02 09:00:00', 1);
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson("/api/v1/movements/{$movement->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $movement->id)
        ->assertJsonPath('data.items.0.quantity', 10)
        ->assertJsonPath('data.items.0.product.id', $product->id);

    $this
        ->withToken($token)
        ->getJson("/api/v1/movements/{$foreignMovement->id}")
        ->assertNotFound();
});

it('updates a movement through the api reusing movement rules', function () {
    CarbonImmutable::setTestNow('2026-10-10 12:00:00');
    [$company, $user, , $contract, $contractItem] = apiMovementFixture();
    $movement = createApiMovement($company, $contract, $contractItem, MovementType::Withdrawal, '2026-10-02 09:00:00', 10);
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->patchJson("/api/v1/movements/{$movement->id}", [
            'occurred_at' => '2026-10-02 10:00:00',
            'notes' => 'Correção mobile',
            'items' => [
                ['contract_item_id' => $contractItem->id, 'quantity' => 8, 'equipment_id' => null],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Correção mobile')
        ->assertJsonPath('data.items.0.quantity', 8);

    CarbonImmutable::setTestNow();
});

it('keeps existing contract movement creation endpoint working', function () {
    CarbonImmutable::setTestNow('2026-10-10 12:00:00');
    [$company, $user, , $contract, $contractItem] = apiMovementFixture();
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson("/api/v1/contracts/{$contract->id}/movements", [
            'type' => MovementType::Withdrawal->value,
            'occurred_at' => '2026-10-02 09:00:00',
            'items' => [
                ['contract_item_id' => $contractItem->id, 'quantity' => 5, 'equipment_id' => null],
            ],
        ])
        ->assertCreated();

    expect(Movement::query()->where('company_id', $company->id)->count())->toBe(1);
    CarbonImmutable::setTestNow();
});
