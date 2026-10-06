<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;

it('searches mobile clients by accented name phone and document within the company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create([
        'name' => 'João da Silva', 'phone' => '(94) 99999-9999', 'document' => '123.456.789-00',
    ]);
    Client::factory()->for(Company::factory()->create())->create(['name' => 'João da Silva']);
    $this->withToken($user->createToken('Search test')->plainTextToken);
    foreach (['João', 'joao', '(94) 99999', '999999999', '12345678900', '123.456.789-00'] as $term) {
        $this->getJson('/api/v1/clients?search='.urlencode($term))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $client->id);
    }
    $this->getJson('/api/v1/clients?search=no-match')->assertOk()->assertJsonCount(0, 'data');
});

it('paginates filtered mobile clients without loading all records', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    Client::factory()->count(17)->for($company)->create(['name' => 'Cliente paginado']);
    Client::factory()->for($company)->create(['name' => 'Outro cliente']);
    $this->withToken($user->createToken('Pagination test')->plainTextToken);
    $this->getJson('/api/v1/clients?search=paginado&page=1')->assertOk()
        ->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 17)->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/v1/clients?search=paginado&page=2')->assertOk()
        ->assertJsonCount(2, 'data')->assertJsonPath('meta.current_page', 2);
});
