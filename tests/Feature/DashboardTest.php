<?php

use App\Actions\Contracts\CreateContractAction;
use App\Actions\Movements\CreateMovementAction;
use App\Actions\Payments\RegisterContractPaymentAction;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard lists returned contracts with pending balances', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00', 'America/Belem'));
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create(['name' => 'Cliente devolvido']);
    $product = Product::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-05 12:00',
        'charge_saturdays' => true,
        'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 1]],
    ]);
    app(CreateMovementAction::class)->handle($contract, [
        'type' => 'RETURN',
        'occurred_at' => '2026-10-06 14:00',
        'items' => [['contract_item_id' => $contract->items()->first()->id, 'quantity' => 1]],
    ]);

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.returned_pending', 1)
            ->has('returnedPending', 1)
            ->where('returnedPending.0.contract_id', $contract->id)
            ->where('returnedPending.0.client', 'Cliente devolvido')
            ->where('returnedPending.0.balance', '20.00'));
});

test('dashboard does not list returned contracts settled as pending', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00', 'America/Belem'));
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-05 12:00',
        'charge_saturdays' => true,
        'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 1]],
    ]);
    app(CreateMovementAction::class)->handle($contract, [
        'type' => 'RETURN',
        'occurred_at' => '2026-10-06 14:00',
        'items' => [['contract_item_id' => $contract->items()->first()->id, 'quantity' => 1]],
    ]);
    app(RegisterContractPaymentAction::class)->handle($contract, [
        'amount' => '20.00',
        'paid_at' => now()->toIso8601String(),
        'method' => 'PIX',
    ]);

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.returned_pending', 0)
            ->has('returnedPending', 0));
});
