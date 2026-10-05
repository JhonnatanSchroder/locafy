<?php

use App\Enums\EquipmentStatus;
use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists equipments from the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create([
        'name' => 'Betoneira',
    ]);
    $equipment = Equipment::factory()->for($company)->for($product)->create([
        'name' => 'Betoneira 01',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('equipments.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipments/Index')
            ->has('equipments.data', 1)
            ->where('equipments.data.0.id', $equipment->id)
            ->where('equipments.data.0.name', 'Betoneira 01')
            ->where('equipments.data.0.product.name', 'Betoneira'));
});

it('does not list equipments from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create();
    $otherProduct = Product::factory()->for($otherCompany)->individual()->create();

    Equipment::factory()->for($company)->for($product)->create([
        'name' => 'Visible Equipment',
    ]);
    Equipment::factory()->for($otherCompany)->for($otherProduct)->create([
        'name' => 'Hidden Equipment',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('equipments.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('equipments.data', 1)
            ->where('equipments.data.0.name', 'Visible Equipment'));
});

it('creates an equipment for the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('equipments.store'), [
            'product_id' => $product->id,
            'name' => 'Betoneira 01',
            'brand' => 'Menegotti',
            'notes' => 'Equipamento novo',
            'status' => EquipmentStatus::Available->value,
        ]);

    $equipment = Equipment::query()->where('name', 'Betoneira 01')->firstOrFail();

    $response->assertRedirect(route('equipments.show', $equipment));
    expect($equipment->company_id)->toBe($company->id);
    expect($equipment->product_id)->toBe($product->id);
    expect($equipment->status)->toBe(EquipmentStatus::Available);
});

it('ignores manually submitted company ids when creating an equipment', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create();

    $this
        ->actingAs($user)
        ->post(route('equipments.store'), [
            'company_id' => $otherCompany->id,
            'product_id' => $product->id,
            'name' => 'Betoneira 02',
            'status' => EquipmentStatus::Available->value,
        ])
        ->assertRedirect();

    $equipment = Equipment::query()->where('name', 'Betoneira 02')->firstOrFail();

    expect($equipment->company_id)->toBe($company->id);
    expect($equipment->company_id)->not->toBe($otherCompany->id);
});

it('shows an equipment from the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create();
    $equipment = Equipment::factory()->for($company)->for($product)->create([
        'name' => 'Betoneira Visível',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('equipments.show', $equipment));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipments/Show')
            ->where('equipment.id', $equipment->id)
            ->where('equipment.name', 'Betoneira Visível'));
});

it('returns not found when showing an equipment from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $otherProduct = Product::factory()->for($otherCompany)->individual()->create();
    $equipment = Equipment::factory()->for($otherCompany)->for($otherProduct)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('equipments.show', $equipment));

    $response->assertNotFound();
});

it('updates an equipment from the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create();
    $equipment = Equipment::factory()->for($company)->for($product)->create([
        'name' => 'Nome Antigo',
        'status' => EquipmentStatus::Available,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('equipments.update', $equipment), [
            'product_id' => $product->id,
            'name' => 'Nome Atualizado',
            'brand' => 'CSM',
            'notes' => 'Atualizado',
            'status' => EquipmentStatus::Maintenance->value,
        ]);

    $response->assertRedirect(route('equipments.show', $equipment));
    expect($equipment->refresh()->name)->toBe('Nome Atualizado');
    expect($equipment->brand)->toBe('CSM');
    expect($equipment->status)->toBe(EquipmentStatus::Maintenance);
});

it('returns not found when updating an equipment from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create();
    $otherProduct = Product::factory()->for($otherCompany)->individual()->create();
    $equipment = Equipment::factory()->for($otherCompany)->for($otherProduct)->create([
        'name' => 'Não Deve Atualizar',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('equipments.update', $equipment), [
            'product_id' => $product->id,
            'name' => 'Tentativa Indevida',
            'status' => EquipmentStatus::Available->value,
        ]);

    $response->assertNotFound();
    expect($equipment->refresh()->name)->toBe('Não Deve Atualizar');
});

it('requires an equipment name', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('equipments.create'))
        ->post(route('equipments.store'), [
            'product_id' => $product->id,
            'name' => '',
            'status' => EquipmentStatus::Available->value,
        ]);

    $response
        ->assertSessionHasErrors('name')
        ->assertRedirect(route('equipments.create'));
});

it('rejects invalid equipment statuses', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('equipments.create'))
        ->post(route('equipments.store'), [
            'product_id' => $product->id,
            'name' => 'Status Inválido',
            'status' => 'INVALID',
        ]);

    $response
        ->assertSessionHasErrors('status')
        ->assertRedirect(route('equipments.create'));
});

it('casts the status attribute to an equipment status enum', function () {
    $equipment = Equipment::factory()->create([
        'status' => EquipmentStatus::Rented,
    ]);

    expect($equipment->status)->toBe(EquipmentStatus::Rented);
});

it('forbids users without a company from operating equipments', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('equipments.index'))
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->post(route('equipments.store'), [
            'product_id' => 1,
            'name' => 'Equipamento Sem Empresa',
            'status' => EquipmentStatus::Available->value,
        ])
        ->assertForbidden();

    expect(Equipment::query()->where('name', 'Equipamento Sem Empresa')->exists())->toBeFalse();
});

it('creates equipment with an individual product from the same company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->individual()->create();

    $this
        ->actingAs($user)
        ->post(route('equipments.store'), [
            'product_id' => $product->id,
            'name' => 'Betoneira Permitida',
            'status' => EquipmentStatus::Available->value,
        ])
        ->assertRedirect();

    expect(Equipment::query()->where('name', 'Betoneira Permitida')->exists())->toBeTrue();
});

it('does not create equipment with a quantity product', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create([
        'type' => ProductType::Quantity,
        'stock_total' => 10,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('equipments.create'))
        ->post(route('equipments.store'), [
            'product_id' => $product->id,
            'name' => 'Andaime 001',
            'status' => EquipmentStatus::Available->value,
        ]);

    $response
        ->assertSessionHasErrors('product_id')
        ->assertRedirect(route('equipments.create'));
    expect(Equipment::query()->where('name', 'Andaime 001')->exists())->toBeFalse();
});

it('does not create equipment with a product from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($otherCompany)->individual()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('equipments.create'))
        ->post(route('equipments.store'), [
            'product_id' => $product->id,
            'name' => 'Produto de Outra Empresa',
            'status' => EquipmentStatus::Available->value,
        ]);

    $response
        ->assertSessionHasErrors('product_id')
        ->assertRedirect(route('equipments.create'));
    expect(Equipment::query()->where('name', 'Produto de Outra Empresa')->exists())->toBeFalse();
});

it('does not create equipment with a missing product', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('equipments.create'))
        ->post(route('equipments.store'), [
            'product_id' => 999999,
            'name' => 'Produto Inexistente',
            'status' => EquipmentStatus::Available->value,
        ]);

    $response
        ->assertSessionHasErrors('product_id')
        ->assertRedirect(route('equipments.create'));
    expect(Equipment::query()->where('name', 'Produto Inexistente')->exists())->toBeFalse();
});

it('shows only active individual products from the users company on create', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $eligibleProduct = Product::factory()->for($company)->individual()->create([
        'name' => 'Betoneira Elegível',
        'active' => true,
    ]);
    Product::factory()->for($company)->create([
        'name' => 'Andaime Quantidade',
        'type' => ProductType::Quantity,
        'stock_total' => 10,
    ]);
    Product::factory()->for($company)->individual()->inactive()->create([
        'name' => 'Betoneira Inativa',
    ]);
    Product::factory()->for($otherCompany)->individual()->create([
        'name' => 'Betoneira Outra Empresa',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('equipments.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipments/Create')
            ->has('eligibleProducts', 1)
            ->where('eligibleProducts.0.id', $eligibleProduct->id)
            ->where('eligibleProducts.0.name', 'Betoneira Elegível'));
});

it('includes the current inactive product on edit', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $activeProduct = Product::factory()->for($company)->individual()->create([
        'name' => 'Betoneira Ativa',
        'active' => true,
    ]);
    $inactiveProduct = Product::factory()->for($company)->individual()->inactive()->create([
        'name' => 'Betoneira Atual Inativa',
    ]);
    $equipment = Equipment::factory()->for($company)->for($inactiveProduct)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('equipments.edit', $equipment));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipments/Edit')
            ->has('eligibleProducts', 2)
            ->where('eligibleProducts.0.id', $activeProduct->id)
            ->where('eligibleProducts.1.id', $inactiveProduct->id));
});
