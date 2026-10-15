<?php

use App\Actions\Admin\RecordAdminAuditAction;
use App\Actions\Clients\ChangeClientStatusAction;
use App\Actions\Clients\DeleteClientAction;
use App\Actions\Clients\RestoreClientAction;
use App\Actions\Contracts\CreateContractAction;
use App\Actions\Contracts\DeleteContractAction;
use App\Actions\Movements\CreateMovementAction;
use App\Enums\ClientStatus;
use App\Models\AdminAudit;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Product;
use App\Models\User;
use App\Policies\ContractPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

test('new clients are active and blocked clients cannot receive contracts', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    expect($client->fresh()->status)->toBe(ClientStatus::Active);

    app(ChangeClientStatusAction::class)->handle($client, ClientStatus::Blocked, $user, 'Pendência cadastral');
    $product = Product::factory()->for($company)->create();

    expect(fn () => app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 1]],
    ]))->toThrow(ValidationException::class);
});

test('client with history is archived and history remains available', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 1]],
    ]);

    expect(app(DeleteClientAction::class)->handle($client))->toBe('archived')
        ->and($client->fresh()->trashed())->toBeTrue()
        ->and($contract->fresh()->client->id)->toBe($client->id);
});

test('client without history can be permanently deleted', function () {
    $client = Client::factory()->create();

    expect(app(DeleteClientAction::class)->handle($client))->toBe('deleted')
        ->and(Client::withTrashed()->find($client->id))->toBeNull();
});

test('contract with a movement cannot be permanently deleted', function () {
    test()->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00'));
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 1]],
    ]);
    app(CreateMovementAction::class)->handle($contract, ['type' => 'RETURN', 'occurred_at' => '2026-10-15 11:00', 'items' => [['contract_item_id' => $contract->items()->first()->id, 'quantity' => 1]]]);

    expect(fn () => app(DeleteContractAction::class)->handle($contract, $user, app(RecordAdminAuditAction::class)))->toThrow(ValidationException::class)
        ->and($contract->fresh())->not->toBeNull();
});

test('admin deletes a contract without history and preserves the audit after hard delete', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [],
    ]);

    app(DeleteContractAction::class)->handle($contract, $user, app(RecordAdminAuditAction::class));
    $audit = AdminAudit::query()->where('entity_type', 'contract')->where('entity_id', $contract->id)->firstOrFail();

    expect(Contract::find($contract->id))->toBeNull()
        ->and($audit->company_id)->toBe($company->id)
        ->and($audit->user_id)->toBe($user->id)
        ->and($audit->operation)->toBe('deleted');
});

test('audit failure rolls back contract deletion', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [],
    ]);
    $audit = Mockery::mock(RecordAdminAuditAction::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));

    expect(fn () => app(DeleteContractAction::class)->handle($contract, $user, $audit))->toThrow(RuntimeException::class)
        ->and($contract->fresh())->not->toBeNull();
});

test('contract from another company cannot be deleted by the action', function () {
    $owner = Company::factory()->create();
    $other = Company::factory()->create();
    $user = User::factory()->for($owner)->create();
    $client = Client::factory()->for($other)->create();
    $contract = app(CreateContractAction::class)->handle($other, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [],
    ]);

    expect(fn () => app(DeleteContractAction::class)->handle($contract, $user, app(RecordAdminAuditAction::class)))->toThrow(ModelNotFoundException::class)
        ->and($contract->fresh())->not->toBeNull();
});

test('operator cannot authorize contract deletion', function () {
    $company = Company::factory()->create();
    $operator = User::factory()->for($company)->create(['role' => 'OPERATOR']);
    $client = Client::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [],
    ]);

    expect((new ContractPolicy)->forceDelete($operator, $contract))->toBeFalse();
});

test('administrative status changes, archive and restore are audited', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 1]],
    ]);
    $audit = app(RecordAdminAuditAction::class);

    app(ChangeClientStatusAction::class)->handle($client, ClientStatus::Blocked, $user, 'Cadastro pendente', $audit);
    app(ChangeClientStatusAction::class)->handle($client, ClientStatus::Active, $user, null, $audit);
    app(DeleteClientAction::class)->handle($client, $user, $audit);
    app(RestoreClientAction::class)->handle(Client::withTrashed()->findOrFail($client->id), $user, $audit);

    expect(AdminAudit::query()->where('entity_type', 'client')->pluck('operation')->all())
        ->toBe(['blocked', 'unblocked', 'archived', 'restored'])
        ->and($client->fresh()->status)->toBe(ClientStatus::Inactive)
        ->and(AdminAudit::query()->first()->company_id)->toBe($company->id)
        ->and(AdminAudit::query()->first()->user_id)->toBe($user->id);
});

test('hard deletion remains traceable after the client row is removed', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();

    app(DeleteClientAction::class)->handle($client, $user, app(RecordAdminAuditAction::class));

    expect(Client::withTrashed()->find($client->id))->toBeNull()
        ->and(AdminAudit::query()->where('entity_type', 'client')->where('entity_id', $client->id)->where('operation', 'deleted')->exists())->toBeTrue();
});
