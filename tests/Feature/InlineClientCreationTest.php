<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;

it('creates an inline client with existing web validation and tenant scoping', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $this->actingAs($user)->postJson('/clients', ['type' => 'INDIVIDUAL', 'name' => 'Inline client', 'company_id' => $other->id, 'phone' => '123'])
        ->assertCreated()->assertJsonPath('data.name', 'Inline client');
    expect(Client::where('name', 'Inline client')->first()->company_id)->toBe($company->id);
    $this->postJson('/clients', ['type' => 'INVALID', 'name' => ''])->assertUnprocessable()->assertJsonValidationErrors(['type', 'name']);
    expect(Client::count())->toBe(1);
});

it('requires authentication and a company for inline client creation', function () {
    $this->postJson('/clients', ['type' => 'INDIVIDUAL', 'name' => 'Unauthorized'])->assertUnauthorized();
    $this->actingAs(User::factory()->create(['company_id' => null]))->postJson('/clients', ['type' => 'INDIVIDUAL', 'name' => 'Unauthorized'])->assertForbidden();
    expect(Client::count())->toBe(0);
});
