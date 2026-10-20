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
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\Freight;
use App\Models\Movement;
use App\Models\MovementItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Policies\ContractPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
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

test('contract with movement history and its audit can be permanently deleted', function () {
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

    app(DeleteContractAction::class)->handle($contract, $user, app(RecordAdminAuditAction::class));

    expect(Contract::find($contract->id))->toBeNull()
        ->and(AdminAudit::query()->where('entity_type', 'contract')->where('entity_id', $contract->id)->where('operation', 'deleted')->exists())->toBeTrue();
});

test('permanently deleting a contract removes exclusive financial and operational records', function () {
    Storage::fake('local');
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 2]],
    ]);
    $charge = Charge::create([
        'company_id' => $company->id,
        'contract_id' => $contract->id,
        'rental_amount' => '20.00',
        'freight_amount' => '0.00',
        'total_amount' => '20.00',
        'due_date' => '2026-10-20',
        'calculated_until' => '2026-10-19',
        'status' => 'PAID',
    ]);
    Payment::create([
        'company_id' => $company->id,
        'contract_id' => $contract->id,
        'charge_id' => $charge->id,
        'amount' => '10.00',
        'discount_amount' => '1.00',
        'paid_at' => '2026-10-20 10:00',
        'method' => 'PIX',
    ]);
    Freight::create([
        'company_id' => $company->id,
        'contract_id' => $contract->id,
        'quantity' => 2,
        'unit_amount' => '15.00',
        'occurred_at' => '2026-10-15 10:00',
    ]);
    Storage::disk('local')->put('contracts/test-photo.jpg', 'photo');
    ContractAttachment::create([
        'company_id' => $company->id,
        'contract_id' => $contract->id,
        'uploaded_by' => $admin->id,
        'file_path' => 'contracts/test-photo.jpg',
        'original_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 5,
    ]);
    $itemIds = $contract->items()->pluck('id');

    app(DeleteContractAction::class)->handle($contract, $admin, app(RecordAdminAuditAction::class));

    expect(Contract::find($contract->id))->toBeNull()
        ->and(Movement::where('contract_id', $contract->id)->count())->toBe(0)
        ->and(MovementItem::whereIn('contract_item_id', $itemIds)->count())->toBe(0)
        ->and(Charge::where('contract_id', $contract->id)->count())->toBe(0)
        ->and(Payment::where('contract_id', $contract->id)->count())->toBe(0)
        ->and(Freight::where('contract_id', $contract->id)->count())->toBe(0)
        ->and(ContractAttachment::withTrashed()->where('contract_id', $contract->id)->count())->toBe(0)
        ->and(Client::find($client->id))->not->toBeNull()
        ->and(Product::find($product->id))->not->toBeNull()
        ->and(Storage::disk('local')->missing('contracts/test-photo.jpg'))->toBeTrue()
        ->and(AdminAudit::where('entity_type', 'contract')->where('entity_id', $contract->id)->value('after_state'))->toMatchArray([
            'payment_count' => 1,
            'charge_count' => 1,
            'freight_count' => 1,
        ]);
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

test('admin can delete an eligible contract through the web route', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create(['role' => 'ADMIN']);
    $client = Client::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [],
    ]);

    $this->actingAs($admin)->delete(route('contracts.destroy', $contract))->assertRedirect(route('contracts.index'));

    expect(Contract::find($contract->id))->toBeNull()
        ->and(AdminAudit::query()->where('entity_type', 'contract')->where('entity_id', $contract->id)->exists())->toBeTrue();
});

test('operator cannot delete a contract through the web route', function () {
    $company = Company::factory()->create();
    $operator = User::factory()->for($company)->create(['role' => 'OPERATOR']);
    $client = Client::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-15 10:00',
        'charge_saturdays' => true,
        'items' => [],
    ]);

    $this->actingAs($operator)->delete(route('contracts.destroy', $contract))->assertForbidden();

    expect(Contract::find($contract->id))->not->toBeNull();
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
