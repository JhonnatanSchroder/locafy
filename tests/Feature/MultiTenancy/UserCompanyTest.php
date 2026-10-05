<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;

it('associates a user with a company', function () {
    $company = Company::factory()->create();

    $user = User::factory()
        ->for($company)
        ->create();

    expect($user->company->is($company))->toBeTrue();
});

it('returns users associated with a company', function () {
    $company = Company::factory()->create();
    $user = User::factory()
        ->for($company)
        ->create();

    $users = $company->users;

    expect($users)->toHaveCount(1);
    expect($users->first()->is($user))->toBeTrue();
});

it('casts the role attribute to a user role enum', function () {
    $user = User::factory()->create([
        'role' => UserRole::Financial,
    ]);

    expect($user->role)->toBe(UserRole::Financial);
});

it('creates admin users by default', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Admin);
});

it('allows an authenticated user to visit the dashboard', function () {
    $company = Company::factory()->create();
    $user = User::factory()
        ->for($company)
        ->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});
