<?php

use App\Enums\BillingPeriod;
use App\Enums\ContractStatus;
use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Freight;
use App\Models\Movement;
use App\Models\MovementItem;
use App\Models\Product;
use App\Models\User;
use App\Services\ContractAccrualService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function contractPayload(Client $client, Product $product, array $overrides = []): array
{
    return [
        'client_id' => $client->id,
        'worksite_address' => 'Rua da Obra, 123',
        'started_at' => '2026-10-05T09:00',
        'charge_saturdays' => true,
        'next_charge_date' => '2026-10-12',
        'notes' => 'Contrato inicial',
        'items' => [
            [
                'product_id' => $product->id,
                'billing_period' => BillingPeriod::Day->value,
                'unit_price' => '10.50',
            ],
        ],
        ...$overrides,
    ];
}

it('lists contracts from the authenticated users company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $contract = Contract::factory()->for($company)->create([
        'client_id' => Client::factory()->for($company)->create()->id,
    ]);
    Contract::factory()->for($otherCompany)->create([
        'client_id' => Client::factory()->for($otherCompany)->create()->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('contracts.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contracts/Index')
            ->has('contracts.data', 1)
            ->where('contracts.data.0.id', $contract->id));
});

it('creates a contract for the authenticated users company with item price snapshots', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create([
        'default_price' => '0.60',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'company_id' => Company::factory()->create()->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.60',
                ],
            ],
        ]));

    $contract = Contract::query()->firstOrFail();

    $response->assertRedirect(route('contracts.show', $contract));
    expect($contract->company_id)->toBe($company->id);
    expect($contract->status)->toBe(ContractStatus::Active);
    expect($contract->items()->first()?->unit_price)->toBe('0.60');

    $product->update(['default_price' => '0.70']);

    expect($contract->items()->first()?->refresh()->unit_price)->toBe('0.60');
});

it('creates an optional initial freight when creating a contract', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'initial_freight' => [
                'quantity' => 2,
                'unit_amount' => '125.50',
                'notes' => 'Entrega inicial',
            ],
        ]))
        ->assertRedirect();

    $contract = Contract::query()->firstOrFail();
    $freight = Freight::query()->firstOrFail();

    expect($freight->company_id)->toBe($company->id);
    expect($freight->contract_id)->toBe($contract->id);
    expect($freight->quantity)->toBe(2);
    expect($freight->unit_amount)->toBe('125.50');
    expect($freight->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-10-05 09:00:00');
    expect($freight->notes)->toBe('Entrega inicial');
});

it('does not create an initial freight when initial freight quantity is zero', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'initial_freight' => [
                'quantity' => 0,
                'unit_amount' => '125.50',
            ],
        ]))
        ->assertRedirect();

    expect(Freight::query()->count())->toBe(0);
});

it('creates an initial withdrawal for twelve quantity products', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create(['name' => 'Andaime']);

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'started_at' => '2026-10-05T09:00',
            'items' => [
                [
                    'product_id' => $product->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.60',
                    'initial_quantity' => 12,
                ],
            ],
        ]))
        ->assertRedirect();

    $contract = Contract::query()->firstOrFail();
    $movement = Movement::query()->firstOrFail();
    $movementItem = MovementItem::query()->firstOrFail();

    expect($movement->company_id)->toBe($contract->company_id);
    expect($movement->contract_id)->toBe($contract->id);
    expect($movement->type)->toBe(MovementType::Withdrawal);
    expect($movement->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-10-05 09:00:00');
    expect($movementItem->quantity)->toBe(12);
    expect($movementItem->equipment_id)->toBeNull();
});

it('creates a single initial movement with multiple quantity items', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $scaffold = Product::factory()->for($company)->create(['name' => 'Andaime']);
    $wheel = Product::factory()->for($company)->create(['name' => 'Rodinha']);

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $scaffold, [
            'items' => [
                [
                    'product_id' => $scaffold->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.60',
                    'initial_quantity' => 12,
                ],
                [
                    'product_id' => $wheel->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '1.20',
                    'initial_quantity' => 4,
                ],
            ],
        ]))
        ->assertRedirect();

    expect(Movement::query()->count())->toBe(1);
    expect(MovementItem::query()->count())->toBe(2);
    expect(MovementItem::query()->sum('quantity'))->toBe(16);
});

it('does not create movement items for zero initial quantities', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $scaffold = Product::factory()->for($company)->create();
    $wheel = Product::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $scaffold, [
            'items' => [
                [
                    'product_id' => $scaffold->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.60',
                    'initial_quantity' => 12,
                ],
                [
                    'product_id' => $wheel->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '1.20',
                    'initial_quantity' => 0,
                ],
            ],
        ]))
        ->assertRedirect();

    expect(Movement::query()->count())->toBe(1);
    expect(MovementItem::query()->count())->toBe(1);
    expect(ContractItem::query()->count())->toBe(2);
});

it('does not create an empty movement when every initial quantity is zero', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.60',
                    'initial_quantity' => 0,
                ],
            ],
        ]))
        ->assertRedirect();

    expect(Movement::query()->count())->toBe(0);
    expect(MovementItem::query()->count())->toBe(0);
});

it('does not store initial quantity on contract items', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.60',
                    'initial_quantity' => 12,
                ],
            ],
        ]))
        ->assertRedirect();

    expect(ContractItem::query()->firstOrFail()->getAttributes())->not->toHaveKey('initial_quantity');
});

it('rejects a client from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($otherCompany)->create();
    $product = Product::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->from(route('contracts.create'))
        ->post(route('contracts.store'), contractPayload($client, $product))
        ->assertSessionHasErrors('client_id');
});

it('rejects a product from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($otherCompany)->create();

    $this
        ->actingAs($user)
        ->from(route('contracts.create'))
        ->post(route('contracts.store'), contractPayload($client, $product))
        ->assertSessionHasErrors('items.0.product_id');
});

it('rejects weekly or monthly periods for quantity products', function (BillingPeriod $period) {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create([
        'type' => ProductType::Quantity,
    ]);

    $this
        ->actingAs($user)
        ->from(route('contracts.create'))
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'billing_period' => $period->value,
                    'unit_price' => '10.00',
                ],
            ],
        ]))
        ->assertSessionHasErrors('items.0.billing_period');
})->with([BillingPeriod::Week, BillingPeriod::Month]);

it('accepts all billing periods for individual products', function (BillingPeriod $period) {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create();

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'billing_period' => $period->value,
                    'unit_price' => '100.00',
                ],
            ],
        ]))
        ->assertRedirect();

    expect(ContractItem::query()->latest('id')->first()?->billing_period)->toBe($period);
})->with([BillingPeriod::Day, BillingPeriod::Week, BillingPeriod::Month]);

it('rejects duplicated products in the same contract', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->from(route('contracts.create'))
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'items' => [
                ['product_id' => $product->id, 'billing_period' => BillingPeriod::Day->value, 'unit_price' => '10.00'],
                ['product_id' => $product->id, 'billing_period' => BillingPeriod::Day->value, 'unit_price' => '11.00'],
            ],
        ]))
        ->assertSessionHasErrors('items.0.product_id');
});

it('returns not found when showing a contract from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $contract = Contract::factory()->for($otherCompany)->create([
        'client_id' => Client::factory()->for($otherCompany)->create()->id,
    ]);

    $this
        ->actingAs($user)
        ->get(route('contracts.show', $contract))
        ->assertNotFound();
});

it('adds freight to a contract from the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $contract = Contract::factory()->for($company)->create(['client_id' => $client->id]);

    $this
        ->actingAs($user)
        ->from(route('contracts.show', $contract))
        ->post(route('contracts.freights.store', $contract), [
            'quantity' => 3,
            'unit_amount' => '15.00',
            'occurred_at' => '2026-10-06T11:00',
            'notes' => 'Frete complementar',
        ])
        ->assertRedirect(route('contracts.show', $contract));

    $freight = Freight::query()->firstOrFail();

    expect($freight->company_id)->toBe($company->id);
    expect($freight->contract_id)->toBe($contract->id);
    expect($freight->quantity)->toBe(3);
    expect($freight->unit_amount)->toBe('15.00');
});

it('summarizes freight count and totals from quantity times unit amount', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->for($company)->create();
    $contract = Contract::factory()->for($company)->create(['client_id' => $client->id]);

    Freight::factory()->for($company)->for($contract)->create([
        'quantity' => 2,
        'unit_amount' => '15.00',
    ]);
    Freight::factory()->for($company)->for($contract)->create([
        'quantity' => 3,
        'unit_amount' => '20.00',
    ]);

    $summary = app(ContractAccrualService::class)->summarize($contract);

    expect($summary['freight_count'])->toBe(5);
    expect($summary['freight_total'])->toBe('90.00');
});

it('migrates legacy freight amounts to unit amount with quantity one', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->for($company)->create();
    $contract = Contract::factory()->for($company)->create(['client_id' => $client->id]);
    $migration = include database_path('migrations/2026_10_05_180051_update_freights_to_quantity_and_unit_amount.php');

    $migration->down();

    DB::table('freights')->insert([
        'company_id' => $company->id,
        'contract_id' => $contract->id,
        'amount' => '45.00',
        'occurred_at' => '2026-10-05 09:00:00',
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    expect(DB::table('freights')->where('contract_id', $contract->id)->value('quantity'))->toBe(1);
    expect((float) DB::table('freights')->where('contract_id', $contract->id)->value('unit_amount'))->toBe(45.0);
});

it('returns not found when adding freight to another company contract', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $contract = Contract::factory()->for($otherCompany)->create([
        'client_id' => Client::factory()->for($otherCompany)->create()->id,
    ]);

    $this
        ->actingAs($user)
        ->post(route('contracts.freights.store', $contract), [
            'quantity' => 1,
            'unit_amount' => '80.00',
            'occurred_at' => '2026-10-06T11:00',
        ])
        ->assertNotFound();

    expect(Freight::query()->count())->toBe(0);
});

it('updates the initial freight quantity and unit amount when editing a contract', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $contract = Contract::factory()->for($company)->create([
        'client_id' => $client->id,
        'started_at' => '2026-10-05 09:00:00',
    ]);
    $item = ContractItem::factory()->for($contract)->for($product)->create();
    $initialFreight = Freight::factory()->for($company)->for($contract)->create([
        'quantity' => 2,
        'unit_amount' => '15.00',
        'occurred_at' => '2026-10-05 09:00:00',
    ]);
    Freight::factory()->for($company)->for($contract)->create([
        'quantity' => 1,
        'unit_amount' => '50.00',
        'occurred_at' => '2026-10-06 09:00:00',
    ]);

    $this
        ->actingAs($user)
        ->patch(route('contracts.update', $contract), [
            ...contractPayload($client, $product),
            'status' => ContractStatus::Active->value,
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $product->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '10.50',
                ],
            ],
            'initial_freight' => [
                'quantity' => 3,
                'unit_amount' => '20.00',
            ],
        ])
        ->assertRedirect(route('contracts.show', $contract));

    expect($initialFreight->refresh()->quantity)->toBe(3);
    expect($initialFreight->unit_amount)->toBe('20.00');
    expect($contract->freights()->count())->toBe(2);
});

it('rejects an update item id from another contract', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $contract = Contract::factory()->for($company)->create(['client_id' => $client->id]);
    $otherContract = Contract::factory()->for($company)->create(['client_id' => $client->id]);
    $otherItem = ContractItem::factory()->for($otherContract)->for($product)->create();

    $this
        ->actingAs($user)
        ->from(route('contracts.edit', $contract))
        ->patch(route('contracts.update', $contract), [
            ...contractPayload($client, $product),
            'status' => ContractStatus::Active->value,
            'items' => [
                [
                    'id' => $otherItem->id,
                    'product_id' => $product->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '12.00',
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.id');
});

it('updates contract items without recreating existing item identities', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $newProduct = Product::factory()->for($company)->individual()->create();
    $contract = Contract::factory()->for($company)->create(['client_id' => $client->id]);
    $item = ContractItem::factory()->for($contract)->for($product)->create([
        'unit_price' => '10.00',
    ]);

    $this
        ->actingAs($user)
        ->patch(route('contracts.update', $contract), [
            ...contractPayload($client, $product),
            'status' => ContractStatus::Active->value,
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $product->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '15.00',
                ],
                [
                    'product_id' => $newProduct->id,
                    'billing_period' => BillingPeriod::Week->value,
                    'unit_price' => '80.00',
                ],
            ],
        ])
        ->assertRedirect(route('contracts.show', $contract));

    expect($item->refresh()->unit_price)->toBe('15.00');
    expect($contract->items()->whereKey($item->id)->exists())->toBeTrue();
    expect($contract->items()->count())->toBe(2);
});

it('prevents movement items from referencing contract items from another contract', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $contract = Contract::factory()->for($company)->create(['client_id' => $client->id]);
    $otherContract = Contract::factory()->for($company)->create(['client_id' => $client->id]);
    $movement = Movement::factory()->for($company)->for($contract)->create();
    $otherItem = ContractItem::factory()->for($otherContract)->for($product)->create();

    expect(fn () => $movement->items()->create([
        'contract_item_id' => $otherItem->id,
        'quantity' => 1,
        'equipment_id' => null,
    ]))->toThrow(ValidationException::class);
});

it('does not alter the initial withdrawal when editing a contract', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.60',
                    'initial_quantity' => 12,
                ],
            ],
        ]))
        ->assertRedirect();

    $contract = Contract::query()->firstOrFail();
    $contractItem = $contract->items()->firstOrFail();
    $movement = Movement::query()->firstOrFail();
    $movementItem = MovementItem::query()->firstOrFail();

    $this
        ->actingAs($user)
        ->patch(route('contracts.update', $contract), [
            ...contractPayload($client, $product),
            'status' => ContractStatus::Active->value,
            'items' => [
                [
                    'id' => $contractItem->id,
                    'product_id' => $product->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.70',
                    'initial_quantity' => 99,
                ],
            ],
        ])
        ->assertRedirect(route('contracts.show', $contract));

    expect($movement->refresh()->items()->count())->toBe(1);
    expect($movementItem->refresh()->quantity)->toBe(12);
});

it('does not remove contract items that already have movement items', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $scaffold = Product::factory()->for($company)->create();
    $wheel = Product::factory()->for($company)->individual()->create();

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $scaffold, [
            'items' => [
                [
                    'product_id' => $scaffold->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.60',
                    'initial_quantity' => 12,
                ],
                [
                    'product_id' => $wheel->id,
                    'billing_period' => BillingPeriod::Week->value,
                    'unit_price' => '80.00',
                ],
            ],
        ]))
        ->assertRedirect();

    $contract = Contract::query()->firstOrFail();
    $unmovedItem = $contract->items()->whereBelongsTo($wheel)->firstOrFail();

    $this
        ->actingAs($user)
        ->from(route('contracts.edit', $contract))
        ->patch(route('contracts.update', $contract), [
            ...contractPayload($client, $wheel),
            'status' => ContractStatus::Active->value,
            'items' => [
                [
                    'id' => $unmovedItem->id,
                    'product_id' => $wheel->id,
                    'billing_period' => BillingPeriod::Week->value,
                    'unit_price' => '80.00',
                ],
            ],
        ])
        ->assertSessionHasErrors('items');
});

it('continues to remove contract items without movement items', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $scaffold = Product::factory()->for($company)->create();
    $wheel = Product::factory()->for($company)->individual()->create();
    $contract = Contract::factory()->for($company)->create(['client_id' => $client->id]);
    $keptItem = ContractItem::factory()->for($contract)->for($scaffold)->create();
    $removedItem = ContractItem::factory()->for($contract)->for($wheel)->create();

    $this
        ->actingAs($user)
        ->patch(route('contracts.update', $contract), [
            ...contractPayload($client, $scaffold),
            'status' => ContractStatus::Active->value,
            'items' => [
                [
                    'id' => $keptItem->id,
                    'product_id' => $scaffold->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.60',
                ],
            ],
        ])
        ->assertRedirect(route('contracts.show', $contract));

    expect(ContractItem::query()->whereKey($removedItem->id)->exists())->toBeFalse();
});

it('rolls back the whole contract creation when initial movement creation fails', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();

    MovementItem::saving(function (): void {
        throw new RuntimeException('Physical movement failed.');
    });

    $this
        ->actingAs($user)
        ->post(route('contracts.store'), contractPayload($client, $product, [
            'items' => [
                [
                    'product_id' => $product->id,
                    'billing_period' => BillingPeriod::Day->value,
                    'unit_price' => '0.60',
                    'initial_quantity' => 12,
                ],
            ],
        ]))
        ->assertServerError();

    expect(Contract::query()->count())->toBe(0);
    expect(ContractItem::query()->count())->toBe(0);
    expect(Movement::query()->count())->toBe(0);
    expect(MovementItem::query()->count())->toBe(0);
});

it('forbids users without a company from operating contracts', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('contracts.index'))
        ->assertForbidden();
});
