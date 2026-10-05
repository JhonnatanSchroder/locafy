<?php

use App\Enums\ClientType;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;

function apiClientPayload(array $overrides = []): array
{
    return [
        'type' => ClientType::Individual->value,
        'name' => 'Cliente Mobile',
        'document' => '12345678900',
        'phone' => '(91) 99999-0000',
        'residential_address' => 'Rua Mobile, 100',
        'notes' => 'Criado pelo app',
        ...$overrides,
    ];
}

it('creates a client through the api for the authenticated users company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $token = $user->createToken('Mobile')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->postJson('/api/v1/clients', apiClientPayload([
            'company_id' => $otherCompany->id,
            'name' => 'Cliente Criado API',
        ]));

    $client = Client::query()->where('name', 'Cliente Criado API')->firstOrFail();

    $response
        ->assertCreated()
        ->assertJsonPath('data.id', $client->id)
        ->assertJsonPath('data.type', ClientType::Individual->value)
        ->assertJsonPath('data.type_label', 'Pessoa Física')
        ->assertJsonPath('data.name', 'Cliente Criado API')
        ->assertJsonPath('data.document', '12345678900')
        ->assertJsonPath('data.phone', '(91) 99999-0000')
        ->assertJsonPath('data.residential_address', 'Rua Mobile, 100')
        ->assertJsonPath('data.notes', 'Criado pelo app');

    expect($client->company_id)->toBe($company->id);
    expect($client->company_id)->not->toBe($otherCompany->id);
});

it('updates a client through the api for the authenticated users company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create([
        'name' => 'Nome Antigo API',
    ]);
    $token = $user->createToken('Mobile')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson("/api/v1/clients/{$client->id}", apiClientPayload([
            'type' => ClientType::Company->value,
            'name' => 'Nome Atualizado API',
            'document' => '12345678000199',
            'phone' => '(91) 98888-1111',
            'residential_address' => 'Avenida API, 200',
            'notes' => 'Atualizado pelo app',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $client->id)
        ->assertJsonPath('data.type', ClientType::Company->value)
        ->assertJsonPath('data.type_label', 'Pessoa Jurídica')
        ->assertJsonPath('data.name', 'Nome Atualizado API')
        ->assertJsonPath('data.document', '12345678000199')
        ->assertJsonPath('data.phone', '(91) 98888-1111')
        ->assertJsonPath('data.residential_address', 'Avenida API, 200')
        ->assertJsonPath('data.notes', 'Atualizado pelo app');

    expect($client->refresh()->name)->toBe('Nome Atualizado API');
    expect($client->type)->toBe(ClientType::Company);
});

it('returns not found when updating another company client through the api', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($otherCompany)->create([
        'name' => 'Cliente de Outra Empresa',
    ]);
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->patchJson("/api/v1/clients/{$client->id}", apiClientPayload([
            'name' => 'Tentativa Indevida',
        ]))
        ->assertNotFound();

    expect($client->refresh()->name)->toBe('Cliente de Outra Empresa');
});

it('validates client payloads through the api', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson('/api/v1/clients', [
            'type' => 'INVALID',
            'name' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'name']);

    expect(Client::query()->count())->toBe(0);
});

it('requires authentication to write clients through the api', function () {
    $client = Client::factory()->create();

    $this
        ->postJson('/api/v1/clients', apiClientPayload())
        ->assertUnauthorized();

    $this
        ->patchJson("/api/v1/clients/{$client->id}", apiClientPayload([
            'name' => 'Sem Token',
        ]))
        ->assertUnauthorized();
});

it('forbids api users without a company from creating clients', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson('/api/v1/clients', apiClientPayload([
            'name' => 'Cliente Sem Empresa',
        ]))
        ->assertForbidden();

    expect(Client::query()->where('name', 'Cliente Sem Empresa')->exists())->toBeFalse();
});
