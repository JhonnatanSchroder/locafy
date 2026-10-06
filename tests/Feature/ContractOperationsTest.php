<?php

use App\Actions\Charges\CreateChargeAction;
use App\Actions\Contracts\CreateContractAction;
use App\Actions\Movements\CreateMovementAction;
use App\Enums\ContractStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\ContractCalculationService;
use App\Services\ContractFinanceService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

function operationalContract(): array
{
    test()->travelTo(CarbonImmutable::parse('2026-10-07 18:00', 'America/Belem'));
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create(['name' => 'Cliente operacional']);
    $product = Product::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id, 'started_at' => '2026-10-05 12:00', 'charge_saturdays' => true,
        'next_charge_date' => '2026-10-07', 'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 2]],
    ]);

    return [$user, $contract];
}

function operationalReturn($contract, int $quantity = 2, string $at = '2026-10-06 14:00'): void
{
    app(CreateMovementAction::class)->handle($contract, ['type' => 'RETURN', 'occurred_at' => $at, 'items' => [['contract_item_id' => $contract->items()->first()->id, 'quantity' => $quantity]]]);
}

function operationalPay(string $amount): array
{
    return ['amount' => $amount, 'paid_at' => now()->toIso8601String(), 'method' => 'PIX', 'notes' => 'Recebimento'];
}

it('keeps active contracts with physical items and marks only the last return as returned', function () {
    [$user, $contract] = operationalContract();
    operationalReturn($contract, 1);
    expect($contract->fresh()->status)->toBe(ContractStatus::Active)->and($contract->fresh()->ended_at)->toBeNull();
    operationalReturn($contract, 1, '2026-10-07 09:00');
    expect($contract->fresh()->status)->toBe(ContractStatus::Returned)->and($contract->fresh()->ended_at->format('Y-m-d H:i'))->toBe('2026-10-07 09:00');
    $this->actingAs($user)->getJson("/api/v1/contracts/{$contract->id}")->assertJsonPath('data.display_status', 'PAYMENT_PENDING')->assertJsonPath('data.items.0.current_quantity', 0);
});

it('freezes returned rental at the actual retroactive return without changing the return day rule', function () {
    [$user, $contract] = operationalContract();
    operationalReturn($contract);
    expect(app(ContractFinanceService::class)->summarize($contract->fresh())['rental_total'])->toBe('40.00');
    $this->travelTo(CarbonImmutable::parse('2026-10-20 18:00', 'America/Belem'));
    expect(app(ContractFinanceService::class)->summarize($contract->fresh())['rental_total'])->toBe('40.00');
    expect($contract->fresh()->ended_at->format('Y-m-d H:i'))->toBe('2026-10-06 14:00');
});

it('uses today without an end and the stored past end as a strict calculation limit', function () {
    [, $contract] = operationalContract();
    $calculator = app(ContractCalculationService::class);
    expect($calculator->calculate($contract)->rentalTotal)->toBe('60.00');
    $contract->update(['ended_at' => '2026-10-05 18:00']);
    expect($calculator->calculate($contract)->rentalTotal)->toBe('20.00')->and($calculator->calculate($contract)->calculatedUntil)->toBe('2026-10-05');
});

it('blocks finalization with items or an unpaid balance and returns clear errors', function () {
    [$user, $contract] = operationalContract();
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/finalize")->assertUnprocessable()->assertJsonValidationErrors('status');
    operationalReturn($contract);
    $this->postJson("/api/v1/contracts/{$contract->id}/finalize")->assertUnprocessable()->assertJsonValidationErrors('status')->assertJsonPath('errors.status.0', 'Quite o saldo atual de R$ 40,00 antes de finalizar.');
    expect($contract->fresh()->status)->toBe(ContractStatus::Returned);
});

it('updates quick payments and becomes ready without finalizing automatically', function () {
    [$user, $contract] = operationalContract();
    operationalReturn($contract);
    $this->actingAs($user)->from('/contracts?status=RETURNED&page=2')->post("/contracts/{$contract->id}/payments", operationalPay('10.00'))->assertRedirect('/contracts?status=RETURNED&page=2');
    $this->getJson("/api/v1/contracts/{$contract->id}")->assertJsonPath('data.total_paid', '10.00')->assertJsonPath('data.financial_balance', '30.00')->assertJsonPath('data.display_status_label', 'Pendente de pagamento')->assertJsonPath('data.next_charge_date', '2026-10-07');
    $this->postJson("/api/v1/contracts/{$contract->id}/payments", operationalPay('30.00'))->assertOk()->assertJsonPath('data.can_finalize', true)->assertJsonPath('data.display_status_label', 'Pronto para finalizar');
    expect($contract->fresh()->status)->toBe(ContractStatus::Returned);
    $this->getJson('/api/v1/charges')->assertJsonCount(0, 'data');
});

it('filters contracts by operational derived states', function () {
    [$user, $active] = operationalContract();
    $company = $user->company;
    $client = Client::factory()->for($company)->create(['name' => 'Filtro']);
    $product = Product::factory()->for($company)->create();

    $returnedPending = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id, 'started_at' => '2026-10-05 12:00', 'charge_saturdays' => true,
        'next_charge_date' => '2026-10-07', 'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 1]],
    ]);
    operationalReturn($returnedPending, 1);

    $ready = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id, 'started_at' => '2026-10-05 12:00', 'charge_saturdays' => true,
        'next_charge_date' => '2026-10-07', 'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 1]],
    ]);
    operationalReturn($ready, 1);
    $this->actingAs($user)->postJson("/api/v1/contracts/{$ready->id}/payments", operationalPay('20.00'))->assertOk();

    $finalized = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id, 'started_at' => '2026-10-05 12:00', 'charge_saturdays' => true,
        'next_charge_date' => '2026-10-07', 'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00', 'initial_quantity' => 1]],
    ]);
    operationalReturn($finalized, 1);
    $this->postJson("/api/v1/contracts/{$finalized->id}/payments", operationalPay('20.00'))->assertOk();
    $this->postJson("/api/v1/contracts/{$finalized->id}/finalize")->assertOk();

    $cancelled = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id, 'started_at' => '2026-10-05 12:00', 'charge_saturdays' => true,
        'next_charge_date' => '2026-10-07', 'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00']],
    ]);
    $cancelled->update(['status' => ContractStatus::Cancelled]);

    $this->get(route('contracts.index', ['status' => 'ACTIVE']))
        ->assertInertia(fn (Assert $page) => $page->has('contracts.data', 1)->where('contracts.data.0.id', $active->id));
    $this->get(route('contracts.index', ['status' => 'PAYMENT_PENDING']))
        ->assertInertia(fn (Assert $page) => $page->has('contracts.data', 1)->where('contracts.data.0.id', $returnedPending->id)->where('contracts.data.0.display_status_label', 'Pendente de pagamento'));
    $this->get(route('contracts.index', ['status' => 'READY_TO_FINALIZE']))
        ->assertInertia(fn (Assert $page) => $page->has('contracts.data', 1)->where('contracts.data.0.id', $ready->id)->where('contracts.data.0.display_status_label', 'Pronto para finalizar'));
    $this->get(route('contracts.index', ['status' => 'FINALIZED']))
        ->assertInertia(fn (Assert $page) => $page->has('contracts.data', 1)->where('contracts.data.0.id', $finalized->id));
    $this->get(route('contracts.index', ['status' => 'CANCELLED']))
        ->assertInertia(fn (Assert $page) => $page->has('contracts.data', 1)->where('contracts.data.0.id', $cancelled->id));
});

it('explicitly finalizes preserves the physical end and freezes all totals on subsequent days', function () {
    [$user, $contract] = operationalContract();
    operationalReturn($contract);
    $end = $contract->fresh()->ended_at->toIso8601String();
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/payments", operationalPay('40.00'))->assertOk();
    $this->postJson("/api/v1/contracts/{$contract->id}/finalize")->assertOk()->assertJsonPath('data.status', 'FINALIZED')->assertJsonPath('data.financial_balance', '0.00');
    expect($contract->fresh()->ended_at->toIso8601String())->toBe($end);
    $this->travelTo(CarbonImmutable::parse('2026-11-15 18:00', 'America/Belem'));
    $this->getJson("/api/v1/contracts/{$contract->id}")->assertJsonPath('data.rental_total', '40.00')->assertJsonPath('data.total_accrued', '40.00')->assertJsonPath('data.financial_balance', '0.00');
});

it('fills a missing end from historical returns on finalization and repairs legacy active states', function () {
    [$user, $contract] = operationalContract();
    operationalReturn($contract);
    $contract->refresh()->update(['status' => 'ACTIVE', 'ended_at' => null]);
    Payment::create(['company_id' => $user->company_id, 'contract_id' => $contract->id, ...operationalPay('40.00')]);
    $this->actingAs($user)->get('/contracts')->assertInertia(fn (Assert $p) => $p->where('contracts.data.0.status', 'RETURNED')->where('contracts.data.0.can_finalize', true));
    $contract->refresh()->update(['ended_at' => null]);
    $this->postJson("/api/v1/contracts/{$contract->id}/finalize")->assertOk();
    expect($contract->fresh()->ended_at->format('Y-m-d H:i'))->toBe('2026-10-06 14:00');
});

it('uses now as the safe finalization fallback without physical return history', function () {
    [$user, $contract] = operationalContract();
    $contract->movements()->first()->items()->delete();
    $contract->movements()->delete();
    $contract->update(['status' => 'RETURNED']);
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/finalize")->assertOk();
    expect($contract->fresh()->ended_at->timestamp)->toBe(now()->timestamp);
});

it('blocks every operational mutation of finalized contracts and repeated finalization', function () {
    [$user, $contract] = operationalContract();
    operationalReturn($contract);
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/payments", operationalPay('40.00'));
    $this->postJson("/api/v1/contracts/{$contract->id}/finalize")->assertOk();
    foreach (['WITHDRAWAL', 'RETURN'] as $type) {
        $this->postJson("/api/v1/contracts/{$contract->id}/movements", ['type' => $type, 'occurred_at' => now()->toIso8601String(), 'items' => [['contract_item_id' => $contract->items()->first()->id, 'quantity' => 1]]])->assertUnprocessable();
    }
    $this->postJson("/api/v1/contracts/{$contract->id}/freights", ['quantity' => 1, 'unit_amount' => '10.00', 'occurred_at' => now()->toIso8601String()])->assertUnprocessable();
    $this->patchJson("/api/v1/contracts/{$contract->id}", ['status' => 'ACTIVE'])->assertUnprocessable();
    $this->postJson("/api/v1/contracts/{$contract->id}/finalize")->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('protects finalization authentication tenant isolation and cancelled contracts', function () {
    [$user, $contract] = operationalContract();
    $this->postJson("/api/v1/contracts/{$contract->id}/finalize")->assertUnauthorized();
    $other = User::factory()->for(Company::factory())->create();
    $this->actingAs($other)->postJson("/api/v1/contracts/{$contract->id}/finalize")->assertNotFound();
    $contract->update(['status' => 'CANCELLED']);
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/finalize")->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('lists payments exactly once newest first and filters with tenant safe exact summaries', function () {
    [$user, $contract] = operationalContract();
    $first = Payment::create(['company_id' => $user->company_id, 'contract_id' => $contract->id, 'amount' => '10.01', 'paid_at' => '2026-10-06 15:00:00', 'method' => 'CASH']);
    $last = Payment::create(['company_id' => $user->company_id, 'contract_id' => $contract->id, 'amount' => '20.02', 'paid_at' => '2026-10-07 15:00:00', 'method' => 'PIX']);
    [$other, $otherContract] = operationalContract();
    Payment::create(['company_id' => $other->company_id, 'contract_id' => $otherContract->id, ...operationalPay('1.00')]);
    $this->actingAs($user)->getJson('/api/v1/payments')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $last->id)->assertJsonPath('data.1.id', $first->id)->assertJsonPath('summary.today', '20.02')->assertJsonPath('summary.month', '30.03');
    $this->getJson('/api/v1/payments?method=PIX&from=2026-10-07&to=2026-10-07&search='.$contract->id)->assertJsonCount(1, 'data')->assertJsonPath('summary.filtered_total', '20.02');
    $this->getJson('/api/v1/payments?search=Cliente')->assertJsonCount(2, 'data');
    $this->get('/pagamentos')->assertOk()->assertInertia(fn (Assert $p) => $p->component('payments/Index')->has('payments.data', 2)->where('summary.count', 2));
    $this->getJson('/api/v1/payments?from=2026-10-08&to=2026-10-07')->assertUnprocessable();
});

it('paginates global payments on the server', function () {
    [$user, $contract] = operationalContract();
    foreach (range(1, 17) as $i) {
        Payment::create(['company_id' => $user->company_id, 'contract_id' => $contract->id, ...operationalPay('0.01')]);
    }
    $this->actingAs($user)->getJson('/api/v1/payments')->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 17)->assertJsonPath('summary.filtered_total', '0.17');
    $this->getJson('/api/v1/payments?page=2')->assertJsonCount(2, 'data');
});

it('normalizes local payment input and computes business date summaries across midnight', function () {
    [$user, $contract] = operationalContract();
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/payments", [
        'amount' => '1.01', 'paid_at' => '2026-10-06T23:30', 'method' => 'PIX',
    ])->assertOk();
    expect(Payment::first()->paid_at->format('Y-m-d H:i'))->toBe('2026-10-07 02:30');
    $this->getJson('/api/v1/payments?from=2026-10-06&to=2026-10-06')->assertJsonCount(1, 'data')->assertJsonPath('summary.today', '0.00');
    $this->getJson('/api/v1/payments?to=2026-10-06')->assertOk()->assertJsonCount(1, 'data');
});

it('finalizes through the web with a success response while preserving list filters', function () {
    [$user, $contract] = operationalContract();
    operationalReturn($contract);
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/payments", operationalPay('40.00'))->assertOk();
    $this->from('/contracts?status=RETURNED&page=2')->post("/contracts/{$contract->id}/finalize")->assertRedirect('/contracts?status=RETURNED&page=2');
    expect($contract->fresh()->status)->toBe(ContractStatus::Finalized);
});

it('retains charge-linked historical payments exactly once in the global list', function () {
    [$user, $contract] = operationalContract();
    $charge = app(CreateChargeAction::class)->handle($contract, ['due_date' => '2026-10-07']);
    $payment = Payment::create(['company_id' => $user->company_id, 'charge_id' => $charge->id, ...operationalPay('10.00')]);
    $this->actingAs($user)->getJson('/api/v1/payments')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $payment->id)->assertJsonPath('data.0.contract_id', $contract->id)->assertJsonPath('summary.filtered_total', '10.00');
});
