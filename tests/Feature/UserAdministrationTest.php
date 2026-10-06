<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('lists only users from the administrators company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $admin = User::factory()->for($company)->create(['role' => UserRole::Admin]);
    User::factory()->for($company)->create(['role' => UserRole::Operator]);
    User::factory()->for($otherCompany)->create(['name' => 'Outra Empresa']);

    $this
        ->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->has('users.data', 2));
});

it('blocks operators from administering users', function () {
    $operator = User::factory()->for(Company::factory())->create(['role' => UserRole::Operator]);

    $this
        ->actingAs($operator)
        ->get(route('users.index'))
        ->assertForbidden();
});

it('creates users in the current company with a hashed temporary password', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create(['role' => UserRole::Admin]);

    $this
        ->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Operador',
            'email' => 'operador@example.com',
            'role' => UserRole::Operator->value,
            'generate_password' => true,
        ])
        ->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'operador@example.com')->firstOrFail();

    expect($user->company_id)->toBe($company->id);
    expect($user->role)->toBe(UserRole::Operator);
    expect($user->password)->not->toBeNull();
    expect(Hash::needsRehash($user->password))->toBeFalse();
    expect($user->must_change_password_at)->not->toBeNull();
    expect($user->created_by_user_id)->toBe($admin->id);
});

it('creates users with an administrator defined password', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create(['role' => UserRole::Admin]);

    $this
        ->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Operador',
            'email' => 'operador-senha@example.com',
            'role' => UserRole::Operator->value,
            'generate_password' => false,
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ])
        ->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'operador-senha@example.com')->firstOrFail();

    expect(Hash::check('Password!123', $user->password))->toBeTrue();
    expect($user->must_change_password_at)->not->toBeNull();
});

it('validates password confirmation when creating a user', function () {
    $admin = User::factory()->for(Company::factory())->create(['role' => UserRole::Admin]);

    $this
        ->actingAs($admin)
        ->from(route('users.index'))
        ->post(route('users.store'), [
            'name' => 'Operador',
            'email' => 'operador-confirmacao@example.com',
            'role' => UserRole::Operator->value,
            'generate_password' => false,
            'password' => 'Password!123',
            'password_confirmation' => 'diferente',
        ])
        ->assertSessionHasErrors('password');
});

it('updates only users from the same company', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create(['role' => UserRole::Admin]);
    $other = User::factory()->for(Company::factory())->create(['role' => UserRole::Operator]);

    $this
        ->actingAs($admin)
        ->patch(route('users.update', $other), [
            'name' => 'Invadido',
            'email' => 'invadido@example.com',
            'role' => UserRole::Operator->value,
        ])
        ->assertNotFound();
});

it('does not deactivate the last active company administrator', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create(['role' => UserRole::Admin]);

    $this
        ->actingAs($admin)
        ->patch(route('users.deactivate', $admin))
        ->assertSessionHasErrors('role');

    expect($admin->refresh()->active)->toBeTrue();
});

it('does not demote the last active company administrator', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create(['role' => UserRole::Admin]);

    $this
        ->actingAs($admin)
        ->from(route('users.index'))
        ->patch(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Operator->value,
        ])
        ->assertSessionHasErrors('role');

    expect($admin->refresh()->role)->toBe(UserRole::Admin);
});

it('deactivates a user and blocks old api tokens', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create(['role' => UserRole::Admin]);
    $operator = User::factory()->for($company)->create(['role' => UserRole::Operator]);
    $token = $operator->createToken('mobile')->plainTextToken;

    $this
        ->actingAs($admin)
        ->patch(route('users.deactivate', $operator))
        ->assertRedirect(route('users.index'));

    expect($operator->refresh()->active)->toBeFalse();
    expect($operator->tokens()->count())->toBe(0);

    auth()->guard('web')->logout();
    $this->flushSession();

    $this
        ->withToken($token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('resets a password without revealing the previous password', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create(['role' => UserRole::Admin]);
    $operator = User::factory()->for($company)->create([
        'role' => UserRole::Operator,
        'password' => Hash::make('old-secret'),
    ]);
    $oldHash = $operator->password;

    $this
        ->actingAs($admin)
        ->post(route('users.reset-password', $operator), [
            'generate_password' => false,
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ])
        ->assertRedirect(route('users.index'));

    expect($operator->refresh()->password)->not->toBe($oldHash);
    expect(Hash::check('NewPassword!123', $operator->password))->toBeTrue();
    expect($operator->must_change_password_at)->not->toBeNull();
});

it('resetting a password revokes api tokens', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create(['role' => UserRole::Admin]);
    $operator = User::factory()->for($company)->create(['role' => UserRole::Operator]);
    $operator->createToken('mobile');

    $this
        ->actingAs($admin)
        ->post(route('users.reset-password', $operator), [
            'generate_password' => true,
        ])
        ->assertRedirect(route('users.index'));

    expect($operator->refresh()->tokens()->count())->toBe(0);
    expect($operator->must_change_password_at)->not->toBeNull();
});

it('requires a newly created user to change password before using the panel', function () {
    $user = User::factory()->for(Company::factory())->create([
        'role' => UserRole::Operator,
        'password' => Hash::make('Password!123'),
        'must_change_password_at' => now(),
    ]);

    $this
        ->post('/login', [
            'email' => $user->email,
            'password' => 'Password!123',
        ])
        ->assertRedirect(route('dashboard'));

    $this
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change.edit'));
});

it('changing the initial password clears the mandatory password flag', function () {
    $user = User::factory()->for(Company::factory())->create([
        'role' => UserRole::Operator,
        'must_change_password_at' => now(),
    ]);

    $this
        ->actingAs($user)
        ->put(route('password.change.update'), [
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ])
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->must_change_password_at)->toBeNull();
    expect(Hash::check('Password!123', $user->password))->toBeTrue();
});

it('api login and me expose must change password without sensitive fields', function () {
    $user = User::factory()->for(Company::factory())->create([
        'role' => UserRole::Operator,
        'password' => Hash::make('Password!123'),
        'must_change_password_at' => now(),
    ]);

    $login = $this
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password!123',
            'device_name' => 'mobile',
        ])
        ->assertOk()
        ->assertJsonPath('must_change_password', true)
        ->assertJsonMissingPath('password')
        ->json();

    $this
        ->withToken($login['token'])
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('must_change_password', true)
        ->assertJsonPath('data.must_change_password', true)
        ->assertJsonMissingPath('data.password');
});

it('inactive users cannot login through the api', function () {
    $user = User::factory()->for(Company::factory())->create([
        'password' => Hash::make('Password!123'),
        'active' => false,
    ]);

    $this
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password!123',
            'device_name' => 'mobile',
        ])
        ->assertForbidden();
});
