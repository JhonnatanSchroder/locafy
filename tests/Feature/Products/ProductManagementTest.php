<?php

use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists products from the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create([
        'name' => 'Andaime',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('products.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->id)
            ->where('products.data.0.name', 'Andaime'));
});

it('does not list products from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    Product::factory()->for($company)->create([
        'name' => 'Visible Product',
    ]);
    Product::factory()->for($otherCompany)->create([
        'name' => 'Hidden Product',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('products.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Visible Product'));
});

it('creates a product for the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Rodinha',
            'type' => ProductType::Quantity->value,
            'default_price' => '1.00',
            'unit' => 'unidade',
            'stock_total' => 10,
            'active' => true,
        ]);

    $product = Product::query()->where('name', 'Rodinha')->firstOrFail();

    $response->assertRedirect(route('products.show', $product));
    expect($product->company_id)->toBe($company->id);
    expect($product->type)->toBe(ProductType::Quantity);
});

it('ignores manually submitted company ids when creating a product', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->post(route('products.store'), [
            'company_id' => $otherCompany->id,
            'name' => 'Tábua',
            'type' => ProductType::Quantity->value,
            'default_price' => '1.00',
            'unit' => 'unidade',
            'stock_total' => 15,
            'active' => true,
        ])
        ->assertRedirect();

    $product = Product::query()->where('name', 'Tábua')->firstOrFail();

    expect($product->company_id)->toBe($company->id);
    expect($product->company_id)->not->toBe($otherCompany->id);
});

it('shows a product from the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create([
        'name' => 'Produto Visível',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('products.show', $product));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/Show')
            ->where('product.id', $product->id)
            ->where('product.name', 'Produto Visível'));
});

it('returns not found when showing a product from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($otherCompany)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('products.show', $product));

    $response->assertNotFound();
});

it('updates a product from the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create([
        'name' => 'Nome Antigo',
        'stock_total' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('products.update', $product), [
            'name' => 'Nome Atualizado',
            'type' => ProductType::Quantity->value,
            'default_price' => '2.50',
            'unit' => 'peça',
            'stock_total' => 20,
            'active' => true,
        ]);

    $response->assertRedirect(route('products.show', $product));
    expect($product->refresh()->name)->toBe('Nome Atualizado');
    expect($product->stock_total)->toBe(20);
});

it('returns not found when updating a product from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($otherCompany)->create([
        'name' => 'Não Deve Atualizar',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('products.update', $product), [
            'name' => 'Tentativa Indevida',
            'type' => ProductType::Quantity->value,
            'stock_total' => 5,
        ]);

    $response->assertNotFound();
    expect($product->refresh()->name)->toBe('Não Deve Atualizar');
});

it('requires a product name', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('products.create'))
        ->post(route('products.store'), [
            'name' => '',
            'type' => ProductType::Quantity->value,
            'stock_total' => 0,
        ]);

    $response
        ->assertSessionHasErrors('name')
        ->assertRedirect(route('products.create'));
});

it('rejects invalid product types', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('products.create'))
        ->post(route('products.store'), [
            'name' => 'Produto Inválido',
            'type' => 'INVALID',
            'stock_total' => 0,
        ]);

    $response
        ->assertSessionHasErrors('type')
        ->assertRedirect(route('products.create'));
});

it('casts the type attribute to a product type enum', function () {
    $product = Product::factory()->create([
        'type' => ProductType::Individual,
        'stock_total' => null,
    ]);

    expect($product->type)->toBe(ProductType::Individual);
});

it('forbids users without a company from operating products', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('products.index'))
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Produto Sem Empresa',
            'type' => ProductType::Quantity->value,
            'stock_total' => 0,
        ])
        ->assertForbidden();

    expect(Product::query()->where('name', 'Produto Sem Empresa')->exists())->toBeFalse();
});

it('allows quantity products to use stock total', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Andaime',
            'type' => ProductType::Quantity->value,
            'stock_total' => 25,
            'active' => true,
        ])
        ->assertRedirect();

    $product = Product::query()->where('name', 'Andaime')->firstOrFail();

    expect($product->stock_total)->toBe(25);
});

it('requires stock total for quantity products', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('products.create'))
        ->post(route('products.store'), [
            'name' => 'Quantidade Sem Estoque',
            'type' => ProductType::Quantity->value,
        ]);

    $response
        ->assertSessionHasErrors('stock_total')
        ->assertRedirect(route('products.create'));
});

it('normalizes stock total to null for individual products', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Betoneira',
            'type' => ProductType::Individual->value,
            'default_price' => null,
            'unit' => 'máquina',
            'stock_total' => 10,
            'active' => true,
        ])
        ->assertRedirect();

    $product = Product::query()->where('name', 'Betoneira')->firstOrFail();

    expect($product->stock_total)->toBeNull();
});
