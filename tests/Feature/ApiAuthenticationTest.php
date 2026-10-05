<?php

use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('logs in and returns a personal access token', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create([
        'email' => 'api@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'api@example.com',
        'password' => 'password',
        'device_name' => 'Alex iPhone',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonStructure(['token', 'token_type']);

    expect(PersonalAccessToken::query()->count())->toBe(1);
    expect($user->tokens()->first()?->name)->toBe('Alex iPhone');
});

it('rejects login for users without a company', function () {
    User::factory()->create([
        'email' => 'no-company@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'no-company@example.com',
        'password' => 'password',
        'device_name' => 'Mobile',
    ]);

    $response->assertForbidden();
    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('requires authentication for protected api routes', function () {
    $this
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('returns the authenticated user', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create([
        'name' => 'API User',
        'email' => 'me@example.com',
    ]);
    $token = $user->createToken('Mobile')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->getJson('/api/v1/me');

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'API User')
        ->assertJsonPath('data.email', 'me@example.com')
        ->assertJsonPath('data.company_id', $company->id);
});

it('revokes only the current token on logout', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $currentToken = $user->createToken('Current device')->plainTextToken;
    $otherToken = $user->createToken('Other device')->accessToken;

    $this
        ->withToken($currentToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    expect(PersonalAccessToken::query()->find($otherToken->id))->not->toBeNull();
    expect(PersonalAccessToken::query()->count())->toBe(1);
});
