<?php

use App\Enums\ClientType;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists clients from the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create([
        'name' => 'Maria Silva',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('clients.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Index')
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $client->id)
            ->where('clients.data.0.name', 'Maria Silva'));
});

it('does not list clients from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    Client::factory()->for($company)->create([
        'name' => 'Visible Client',
    ]);
    Client::factory()->for($otherCompany)->create([
        'name' => 'Hidden Client',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('clients.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.name', 'Visible Client'));
});

it('creates a client for the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('clients.store'), [
            'type' => ClientType::Individual->value,
            'name' => 'João Cliente',
            'document' => '12345678900',
            'phone' => '11999999999',
            'residential_address' => 'Rua Central, 100',
            'notes' => 'Cliente inicial',
        ]);

    $client = Client::query()->where('name', 'João Cliente')->firstOrFail();

    $response->assertRedirect(route('clients.show', $client));
    expect($client->company_id)->toBe($company->id);
    expect($client->type)->toBe(ClientType::Individual);
});

it('ignores manually submitted company ids when creating a client', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $this
        ->actingAs($user)
        ->post(route('clients.store'), [
            'company_id' => $otherCompany->id,
            'type' => ClientType::Company->value,
            'name' => 'Empresa Cliente',
        ])
        ->assertRedirect();

    $client = Client::query()->where('name', 'Empresa Cliente')->firstOrFail();

    expect($client->company_id)->toBe($company->id);
    expect($client->company_id)->not->toBe($otherCompany->id);
});

it('shows a client from the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create([
        'name' => 'Cliente Visível',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('clients.show', $client));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Show')
            ->where('client.id', $client->id)
            ->where('client.name', 'Cliente Visível'));
});

it('returns not found when showing a client from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($otherCompany)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('clients.show', $client));

    $response->assertNotFound();
});

it('updates a client from the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create([
        'name' => 'Nome Antigo',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('clients.update', $client), [
            'type' => ClientType::Company->value,
            'name' => 'Nome Atualizado',
            'document' => '12345678000199',
            'phone' => '1133334444',
            'residential_address' => 'Avenida Nova, 200',
            'notes' => 'Atualizado',
        ]);

    $response->assertRedirect(route('clients.show', $client));
    expect($client->refresh()->name)->toBe('Nome Atualizado');
    expect($client->type)->toBe(ClientType::Company);
});

it('returns not found when updating a client from another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($otherCompany)->create([
        'name' => 'Não Deve Atualizar',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('clients.update', $client), [
            'type' => ClientType::Individual->value,
            'name' => 'Tentativa Indevida',
        ]);

    $response->assertNotFound();
    expect($client->refresh()->name)->toBe('Não Deve Atualizar');
});

it('requires a client name', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('clients.create'))
        ->post(route('clients.store'), [
            'type' => ClientType::Individual->value,
            'name' => '',
        ]);

    $response
        ->assertSessionHasErrors('name')
        ->assertRedirect(route('clients.create'));
});

it('casts the type attribute to a client type enum', function () {
    $client = Client::factory()->create([
        'type' => ClientType::Company,
    ]);

    expect($client->type)->toBe(ClientType::Company);
});

it('forbids users without a company from listing clients', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('clients.index'));

    $response->assertForbidden();
});

it('forbids users without a company from creating clients', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('clients.store'), [
            'type' => ClientType::Individual->value,
            'name' => 'Cliente Sem Empresa',
        ]);

    $response->assertForbidden();
    expect(Client::query()->where('name', 'Cliente Sem Empresa')->exists())->toBeFalse();
});
