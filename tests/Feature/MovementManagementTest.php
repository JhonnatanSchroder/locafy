<?php

use App\Enums\BillingPeriod;
use App\Enums\ContractStatus;
use App\Enums\MovementType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Movement;
use App\Models\Product;
use App\Models\User;
use App\Services\ContractCalculationService;
use Carbon\CarbonImmutable;

function movementContract(Company $company, array $contractOverrides = []): array
{
    $client = Client::factory()->for($company)->create();
    $contract = Contract::factory()->for($company)->create([
        'client_id' => $client->id,
        'started_at' => '2026-10-05 08:00:00',
        'charge_saturdays' => true,
        ...$contractOverrides,
    ]);
    $product = Product::factory()->for($company)->create([
        'name' => 'Andaime',
        'default_price' => '0.60',
    ]);
    $item = ContractItem::factory()->for($contract)->for($product)->create([
        'billing_period' => BillingPeriod::Day,
        'unit_price' => '0.60',
    ]);

    return [$contract, $item, $product];
}

it('creates an additional withdrawal with multiple products', function () {
    CarbonImmutable::setTestNow('2026-10-20 12:00:00');
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    [$contract, $item] = movementContract($company);
    $wheel = Product::factory()->for($company)->create(['name' => 'Rodinha']);
    $wheelItem = ContractItem::factory()->for($contract)->for($wheel)->create(['billing_period' => BillingPeriod::Day]);

    $this
        ->actingAs($user)
        ->post(route('movements.store'), [
            'contract_id' => $contract->id,
            'type' => MovementType::Withdrawal->value,
            'occurred_at' => '2026-10-05T14:00',
            'items' => [
                ['contract_item_id' => $item->id, 'quantity' => 12],
                ['contract_item_id' => $wheelItem->id, 'quantity' => 4],
            ],
        ])
        ->assertRedirect();

    expect(Movement::query()->count())->toBe(1);
    expect(Movement::query()->first()->items()->count())->toBe(2);
    CarbonImmutable::setTestNow();
});

it('rejects returning more than current quantity', function () {
    CarbonImmutable::setTestNow('2026-10-20 12:00:00');
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    [$contract, $item] = movementContract($company);
    $movement = $company->movements()->create(['contract_id' => $contract->id, 'type' => MovementType::Withdrawal, 'occurred_at' => '2026-10-05 14:00:00']);
    $movement->items()->create(['contract_item_id' => $item->id, 'quantity' => 5]);

    $this
        ->actingAs($user)
        ->post(route('movements.store'), [
            'contract_id' => $contract->id,
            'type' => MovementType::Return->value,
            'occurred_at' => '2026-10-06T09:00',
            'items' => [
                ['contract_item_id' => $item->id, 'quantity' => 7],
            ],
        ])
        ->assertSessionHasErrors('items');
    CarbonImmutable::setTestNow();
});

it('allows retroactive movements but not before contract start or in the future', function () {
    CarbonImmutable::setTestNow('2026-10-10 12:00:00');
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    [$contract, $item] = movementContract($company);

    $this
        ->actingAs($user)
        ->post(route('movements.store'), [
            'contract_id' => $contract->id,
            'type' => MovementType::Withdrawal->value,
            'occurred_at' => '2026-10-06T09:00',
            'items' => [['contract_item_id' => $item->id, 'quantity' => 1]],
        ])
        ->assertRedirect();

    $this
        ->actingAs($user)
        ->post(route('movements.store'), [
            'contract_id' => $contract->id,
            'type' => MovementType::Withdrawal->value,
            'occurred_at' => '2026-10-04T09:00',
            'items' => [['contract_item_id' => $item->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('occurred_at');

    $this
        ->actingAs($user)
        ->post(route('movements.store'), [
            'contract_id' => $contract->id,
            'type' => MovementType::Withdrawal->value,
            'occurred_at' => '2026-10-11T09:00',
            'items' => [['contract_item_id' => $item->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('occurred_at');

    CarbonImmutable::setTestNow();
});

it('edits an initial withdrawal and recalculates current quantity', function () {
    CarbonImmutable::setTestNow('2026-10-20 12:00:00');
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    [$contract, $item] = movementContract($company);
    $movement = $company->movements()->create(['contract_id' => $contract->id, 'type' => MovementType::Withdrawal, 'occurred_at' => '2026-10-05 14:00:00']);
    $movement->items()->create(['contract_item_id' => $item->id, 'quantity' => 12]);

    $this
        ->actingAs($user)
        ->patch(route('movements.update', $movement), [
            'occurred_at' => '2026-10-05T14:00',
            'items' => [['contract_item_id' => $item->id, 'quantity' => 10]],
        ])
        ->assertRedirect();

    expect($movement->items()->first()->quantity)->toBe(10);
    CarbonImmutable::setTestNow();
});

it('rejects editing a movement when the timeline would become negative', function () {
    CarbonImmutable::setTestNow('2026-10-20 12:00:00');
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    [$contract, $item] = movementContract($company);
    $withdrawal = $company->movements()->create(['contract_id' => $contract->id, 'type' => MovementType::Withdrawal, 'occurred_at' => '2026-10-05 14:00:00']);
    $withdrawal->items()->create(['contract_item_id' => $item->id, 'quantity' => 10]);
    $return = $company->movements()->create(['contract_id' => $contract->id, 'type' => MovementType::Return, 'occurred_at' => '2026-10-10 09:00:00']);
    $return->items()->create(['contract_item_id' => $item->id, 'quantity' => 8]);

    $this
        ->actingAs($user)
        ->patch(route('movements.update', $withdrawal), [
            'occurred_at' => '2026-10-05T14:00',
            'items' => [['contract_item_id' => $item->id, 'quantity' => 5]],
        ])
        ->assertSessionHasErrors('items');
    CarbonImmutable::setTestNow();
});

it('rejects ending a contract while quantity items are still out and accepts after return', function () {
    CarbonImmutable::setTestNow('2026-10-20 12:00:00');
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    [$contract, $item, $product] = movementContract($company);
    $movement = $company->movements()->create(['contract_id' => $contract->id, 'type' => MovementType::Withdrawal, 'occurred_at' => '2026-10-05 14:00:00']);
    $movement->items()->create(['contract_item_id' => $item->id, 'quantity' => 12]);

    $payload = [
        'client_id' => $contract->client_id,
        'status' => ContractStatus::Active->value,
        'worksite_address' => $contract->worksite_address,
        'started_at' => '2026-10-05T08:00',
        'ended_at' => '2026-10-12T12:00',
        'charge_saturdays' => true,
        'items' => [['id' => $item->id, 'product_id' => $product->id, 'billing_period' => BillingPeriod::Day->value, 'unit_price' => '0.60']],
    ];

    $this->actingAs($user)->patch(route('contracts.update', $contract), $payload)->assertSessionHasErrors('ended_at');

    $return = $company->movements()->create(['contract_id' => $contract->id, 'type' => MovementType::Return, 'occurred_at' => '2026-10-12 09:00:00']);
    $return->items()->create(['contract_item_id' => $item->id, 'quantity' => 12]);

    $this->actingAs($user)->patch(route('contracts.update', $contract), $payload)->assertRedirect();

    CarbonImmutable::setTestNow();
});

it('calculates withdrawal on the same day without ten oclock cutoff', function () {
    CarbonImmutable::setTestNow('2026-10-06 12:00:00');
    $company = Company::factory()->create();
    [$contract, $item] = movementContract($company, ['charge_saturdays' => true]);
    $withdrawal = $company->movements()->create(['contract_id' => $contract->id, 'type' => MovementType::Withdrawal, 'occurred_at' => '2026-10-05 14:00:00']);
    $withdrawal->items()->create(['contract_item_id' => $item->id, 'quantity' => 10]);

    $result = app(ContractCalculationService::class)->calculate($contract->fresh(['items.product', 'items.movementItems.movement', 'movements.items']));

    expect($result->item($item->id)?->billableQuantityDays)->toBe(20);
    expect($result->rentalTotal)->toBe('12.00');

    CarbonImmutable::setTestNow();
});

it('calculates quantity day rentals with return cutoff and weekend rules', function () {
    CarbonImmutable::setTestNow('2026-10-13 12:00:00');
    $company = Company::factory()->create();
    [$contract, $item] = movementContract($company, ['charge_saturdays' => false]);
    $withdrawal = $company->movements()->create(['contract_id' => $contract->id, 'type' => MovementType::Withdrawal, 'occurred_at' => '2026-10-05 09:00:00']);
    $withdrawal->items()->create(['contract_item_id' => $item->id, 'quantity' => 10]);
    $return = $company->movements()->create(['contract_id' => $contract->id, 'type' => MovementType::Return, 'occurred_at' => '2026-10-09 10:00:00']);
    $return->items()->create(['contract_item_id' => $item->id, 'quantity' => 5]);

    $result = app(ContractCalculationService::class)->calculate($contract->fresh(['items.product', 'items.movementItems.movement', 'movements.items']));

    expect($result->calculationComplete)->toBeTrue();
    expect($result->item($item->id)?->billableQuantityDays)->toBe(55);
    expect($result->item($item->id)?->subtotal)->toBe('33.00');
    expect($result->rentalTotal)->toBe('33.00');

    CarbonImmutable::setTestNow();
});
