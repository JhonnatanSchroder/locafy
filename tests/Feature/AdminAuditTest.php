<?php

use App\Enums\UserRole;
use App\Models\AdminAudit;
use App\Models\Company;
use App\Models\User;

function auditUser(UserRole $role = UserRole::Admin): User
{
    $company = Company::factory()->create();

    return User::factory()->for($company)->create(['role' => $role]);
}

it('allows an admin to list and inspect only the company audit history', function () {
    $admin = auditUser();
    $foreign = auditUser();
    $own = AdminAudit::create(['company_id' => $admin->company_id, 'user_id' => $admin->id, 'entity_type' => 'client', 'entity_id' => 10, 'operation' => 'created', 'source' => 'WEB', 'module' => 'clients', 'before_state' => null, 'after_state' => ['name' => 'Cliente'], 'reason' => null]);
    $other = AdminAudit::create(['company_id' => $foreign->company_id, 'user_id' => $foreign->id, 'entity_type' => 'client', 'entity_id' => 99, 'operation' => 'deleted']);

    $this->actingAs($admin)->getJson('/api/v1/audits?module=clients&operation=created')
        ->assertOk()
        ->assertJsonPath('data.0.id', $own->id)
        ->assertJsonMissingPath('data.0.before_state')
        ->assertJsonMissingPath('data.0.after_state');

    $this->getJson("/api/v1/audits/{$own->id}")
        ->assertOk()
        ->assertJsonPath('data.before_state', null)
        ->assertJsonPath('data.after_state.name', 'Cliente');

    $this->getJson("/api/v1/audits/{$other->id}")->assertNotFound();
});

it('denies operators and keeps legacy audit records readable', function () {
    $operator = auditUser(UserRole::Operator);
    $audit = AdminAudit::create(['company_id' => $operator->company_id, 'user_id' => $operator->id, 'entity_type' => 'contract', 'entity_id' => 1, 'operation' => 'deleted']);

    $this->actingAs($operator)->getJson('/api/v1/audits')->assertForbidden();
    $this->get('/auditoria')->assertForbidden();

    $admin = User::factory()->for($operator->company)->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->getJson("/api/v1/audits/{$audit->id}")
        ->assertOk()
        ->assertJsonPath('data.source', null)
        ->assertJsonPath('data.module', null)
        ->assertJsonPath('data.user_snapshot', null);
});

it('paginates audit results in deterministic reverse chronological order', function () {
    $admin = auditUser();
    foreach (range(1, 21) as $entityId) {
        AdminAudit::create(['company_id' => $admin->company_id, 'user_id' => $admin->id, 'entity_type' => 'product', 'entity_id' => $entityId, 'operation' => 'updated']);
    }

    $this->actingAs($admin)->getJson('/api/v1/audits?operation=updated&per_page=20')
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 21)
        ->assertJsonPath('meta.last_page', 2);
});
