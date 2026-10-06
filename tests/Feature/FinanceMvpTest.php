<?php

use App\Actions\Charges\CancelChargeAction;
use App\Actions\Charges\CreateChargeAction;
use App\Actions\Charges\RegisterPaymentAction;
use App\Actions\Contracts\CreateContractAction;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Freight;
use App\Models\Product;
use App\Models\User;
use App\Services\ContractAccrualService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function financeSetup(): array
{
    test()->travelTo(CarbonImmutable::parse('2026-10-05 18:00', 'America/Belem'));
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $payload = ['client_id' => $client->id, 'started_at' => '2026-10-05T12:00', 'charge_saturdays' => true, 'next_charge_date' => '2026-10-05', 'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '70.00', 'initial_quantity' => 1]], 'initial_freight' => ['quantity' => 2, 'unit_amount' => '15.00']];
    $contract = app(CreateContractAction::class)->handle($company, $payload);

    return [$user, $contract, $payload];
}

it('snapshots only new rental and freight components and ignores cancelled charges', function () {
    [$user,$contract] = financeSetup();
    $action = app(CreateChargeAction::class);
    $first = $action->handle($contract, []);
    expect($first->rental_amount)->toBe('70.00')->and($first->freight_amount)->toBe('30.00')->and($first->total_amount)->toBe('100.00');
    Freight::factory()->for($contract)->create(['company_id' => $user->company_id, 'quantity' => 3, 'unit_amount' => '20.00']);
    $second = $action->handle($contract, []);
    expect($second->total_amount)->toBe('60.00')->and($second->rental_amount)->toBe('0.00')->and($second->freight_amount)->toBe('60.00');
    app(CancelChargeAction::class)->handle($second);
    expect($action->handle($contract, [])->total_amount)->toBe('60.00')->and($first->fresh()->total_amount)->toBe('100.00');
});

it('registers exact partial and full payments independently of accrual', function () {
    [, $contract] = financeSetup();
    $charge = app(CreateChargeAction::class)->handle($contract, []);
    $action = app(RegisterPaymentAction::class);
    $data = ['paid_at' => now()->toIso8601String(), 'method' => 'PIX'];
    $action->handle($charge, ['amount' => '40.00', ...$data]);
    expect($charge->fresh()->status)->toBe('PARTIAL');
    $action->handle($charge, ['amount' => '60.00', ...$data]);
    expect($charge->fresh()->status)->toBe('PAID')->and(app(ContractAccrualService::class)->summarize($contract)['total_accrued'])->toBe('100.00');
    expect(fn () => $action->handle($charge, ['amount' => '0.01', ...$data]))->toThrow(ValidationException::class);
    expect(fn () => app(CancelChargeAction::class)->handle($charge))->toThrow(ValidationException::class);
});

it('snapshots rental growth separately and never bills the same accrual twice', function () {
    [, $contract] = financeSetup();
    $action = app(CreateChargeAction::class);
    $action->handle($contract, []);
    expect(fn () => $action->handle($contract, []))->toThrow(ValidationException::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-06 18:00', 'America/Belem'));
    $second = $action->handle($contract, []);
    expect($second->rental_amount)->toBe('70.00')->and($second->freight_amount)->toBe('0.00')->and($second->total_amount)->toBe('70.00');
});

it('edits one web freight preserving its date and all other historical records', function () {
    [$user,$contract] = financeSetup();
    $freight = $contract->freights()->first();
    $other = Freight::factory()->for($contract)->create(['company_id' => $user->company_id, 'quantity' => 1, 'unit_amount' => '5.00']);
    $date = $freight->occurred_at->toDateTimeString();
    $this->actingAs($user)->patch("/contracts/{$contract->id}/freights/{$freight->id}", ['quantity' => 3, 'unit_amount' => '0.20', 'notes' => 'Revised', 'occurred_at' => '2000-01-01'])->assertRedirect();
    expect($freight->fresh()->quantity)->toBe(3)->and($freight->fresh()->occurred_at->toDateTimeString())->toBe($date)->and($other->fresh()->unit_amount)->toBe('5.00');
    $summary = app(ContractAccrualService::class)->summarize($contract->fresh());
    expect($summary['freight_count'])->toBe(4)->and($summary['freight_total'])->toBe('5.60');
    $foreign = Contract::factory()->for($user->company)->create(['client_id' => $contract->client_id]);
    $this->patch("/contracts/{$foreign->id}/freights/{$freight->id}", ['quantity' => 4, 'unit_amount' => '1.00'])->assertNotFound();
});

it('handles cent values without rounding drift and rejects excessive precision', function () {
    [$user,$contract] = financeSetup();
    $contract->items()->update(['unit_price' => '0.10']);
    $contract->freights()->update(['unit_amount' => '0.10']);
    $charge = app(CreateChargeAction::class)->handle($contract, []);
    expect($charge->total_amount)->toBe('0.30');
    $this->actingAs($user)->postJson("/api/v1/charges/history/{$charge->id}/payments", ['amount' => '0.10', 'paid_at' => now()->toIso8601String(), 'method' => 'PIX'])->assertOk()->assertJsonPath('data.balance', '0.20');
    $this->postJson("/api/v1/charges/history/{$charge->id}/payments", ['amount' => '0.201', 'paid_at' => now()->toIso8601String(), 'method' => 'PIX'])->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->postJson("/api/v1/charges/history/{$charge->id}/payments", ['amount' => '0.21', 'paid_at' => now()->toIso8601String(), 'method' => 'PIX'])->assertUnprocessable();
});

it('writes contracts movements freights charges and payments through the api', function () {
    [$user,,$payload] = financeSetup();
    $this->withToken($user->createToken('Mobile')->plainTextToken);
    $created = $this->postJson('/api/v1/contracts', $payload)->assertCreated()->assertJsonPath('data.total_accrued', '100.00');
    $id = $created->json('data.id');
    $this->patchJson("/api/v1/contracts/$id", ['notes' => 'Mobile update'])->assertOk()->assertJsonPath('data.notes', 'Mobile update');
    $contract = Contract::findOrFail($id);
    $item = $contract->items()->first();
    $movement = ['type' => 'WITHDRAWAL', 'occurred_at' => '2026-10-05T15:00', 'items' => [['contract_item_id' => $item->id, 'quantity' => 1]]];
    $this->postJson("/api/v1/contracts/$id/movements", $movement)->assertCreated();
    $this->postJson("/api/v1/contracts/$id/movements", [...$movement, 'type' => 'RETURN', 'occurred_at' => '2026-10-05T16:00'])->assertCreated();
    $freight = $this->postJson("/api/v1/contracts/$id/freights", ['quantity' => 2, 'unit_amount' => '0.10', 'occurred_at' => '2026-10-05T15:00'])->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/contracts/$id/freights/$freight", ['quantity' => 3, 'unit_amount' => '0.20', 'notes' => 'Edited', 'occurred_at' => '2020-01-01'])->assertOk()->assertJsonPath('data.quantity', 3);
    expect(Freight::find($freight)->occurred_at->format('Y-m-d H:i'))->toBe('2026-10-05 15:00');
    $summary = app(ContractAccrualService::class)->summarize($contract->fresh());
    expect($summary['freight_count'])->toBe(5)->and($summary['freight_total'])->toBe('30.60');
    $charge = $this->postJson('/api/v1/charges', ['contract_id' => $id])->assertCreated()->assertJsonPath('data.total_amount', '170.60')->json('data.id');
    $this->getJson('/api/v1/charges')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("/api/v1/charges/$charge")->assertOk();
    $this->postJson("/api/v1/charges/history/$charge/payments", ['amount' => '170.60', 'paid_at' => '2026-10-05T17:00', 'method' => 'PIX'])->assertOk()->assertJsonPath('data.status', 'PAID');
});

it('isolates financial and operation endpoints by tenant', function () {
    [$owner,$contract] = financeSetup();
    $charge = app(CreateChargeAction::class)->handle($contract, []);
    $freight = $contract->freights()->first();
    $other = User::factory()->for(Company::factory())->create();
    $this->actingAs($other);
    $this->getJson('/api/v1/charges')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/charges/{$charge->id}")->assertNotFound();
    $this->postJson("/api/v1/charges/history/{$charge->id}/payments", ['amount' => '1.00', 'paid_at' => now()->toIso8601String(), 'method' => 'PIX'])->assertNotFound();
    $this->postJson('/api/v1/charges', ['contract_id' => $contract->id])->assertUnprocessable();
    $this->patchJson("/api/v1/contracts/{$contract->id}", ['notes' => 'Intrusion'])->assertNotFound();
    $this->postJson("/api/v1/contracts/{$contract->id}/freights", ['quantity' => 1, 'unit_amount' => '1.00', 'occurred_at' => now()->toDateTimeString()])->assertNotFound();
    $this->patchJson("/api/v1/contracts/{$contract->id}/freights/{$freight->id}", ['quantity' => 4, 'unit_amount' => '1.00'])->assertNotFound();
    $this->postJson("/api/v1/contracts/{$contract->id}/movements", ['type' => 'RETURN', 'occurred_at' => now()->toDateTimeString(), 'items' => [['contract_item_id' => $contract->items()->first()->id, 'quantity' => 1]]])->assertNotFound();
    $this->get('/charges')->assertOk()->assertInertia(fn (Assert $p) => $p->has('charges.data', 0));
    $this->get("/charges/{$charge->id}")->assertNotFound();
    expect($charge->payments()->count())->toBe(0)->and($freight->fresh()->quantity)->toBe(2);
});

it('requires authentication for write and financial api routes', function () {
    foreach (['contracts', 'contracts/1/movements', 'contracts/1/freights', 'charges', 'charges/1/payments'] as $path) {
        $this->postJson('/api/v1/'.$path, [])->assertUnauthorized();
    }
    $this->patchJson('/api/v1/contracts/1', [])->assertUnauthorized();
    $this->patchJson('/api/v1/contracts/1/freights/1', [])->assertUnauthorized();
    $this->getJson('/api/v1/charges')->assertUnauthorized();
    $this->getJson('/api/v1/charges/1')->assertUnauthorized();
});

it('only finalizes returned contracts with no financial balance', function () {
    [$user,$contract] = financeSetup();
    $this->actingAs($user)->patchJson("/api/v1/contracts/{$contract->id}", ['status' => 'FINALIZED'])->assertUnprocessable();
    $this->postJson("/api/v1/contracts/{$contract->id}/movements", ['type' => 'RETURN', 'occurred_at' => '2026-10-05T16:00', 'items' => [['contract_item_id' => $contract->items()->first()->id, 'quantity' => 1]]])->assertCreated();
    $this->patchJson("/api/v1/contracts/{$contract->id}", ['ended_at' => '2026-10-05T17:00', 'status' => 'RETURNED'])->assertOk();
    $this->patchJson("/api/v1/contracts/{$contract->id}", ['status' => 'FINALIZED'])->assertUnprocessable();
    $charge = app(CreateChargeAction::class)->handle($contract, []);
    $this->postJson("/api/v1/charges/history/{$charge->id}/payments", ['amount' => $charge->total_amount, 'paid_at' => now()->toIso8601String(), 'method' => 'PIX'])->assertOk();
    $this->patchJson("/api/v1/contracts/{$contract->id}", ['status' => 'FINALIZED'])->assertOk()->assertJsonPath('data.status', 'FINALIZED');
});

it('shows real tenant dashboard metrics and web financial flows', function () {
    [$user,$contract] = financeSetup();
    $this->actingAs($user)->post('/charges', ['contract_id' => $contract->id])->assertRedirect();
    $charge = $contract->charges()->first();
    $this->get('/charges?filter=today')->assertOk()->assertInertia(fn (Assert $p) => $p->component('charges/Index')->has('charges.data', 1));
    $this->get("/charges/history/{$charge->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('charges/HistoryShow')->where('charge.total_amount', '100.00'));
    $this->post("/charges/history/{$charge->id}/payments", ['amount' => '40.00', 'paid_at' => now()->toIso8601String(), 'method' => 'CASH'])->assertRedirect();
    $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p->where('metrics.active', 1)->where('metrics.today', 1)->where('metrics.received', '40.00'));
    $this->patch("/charges/{$charge->id}/cancel")->assertSessionHasErrors('charge');
});
