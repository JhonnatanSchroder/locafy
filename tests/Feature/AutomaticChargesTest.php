<?php

use App\Actions\Charges\CreateChargeAction;
use App\Actions\Contracts\CreateContractAction;
use App\Actions\Movements\CreateMovementAction;
use App\Actions\Payments\RegisterContractPaymentAction;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Freight;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\ContractFinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function liveReceivableFixture(string $due = '2026-10-05'): array
{
    test()->travelTo(CarbonImmutable::parse('2026-10-05 18:00', 'America/Belem'));
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id, 'started_at' => '2026-10-05T12:00', 'charge_saturdays' => true,
        'next_charge_date' => $due, 'charge_interval_days' => 15,
        'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '270.00', 'initial_quantity' => 1]],
        'initial_freight' => ['quantity' => 2, 'unit_amount' => '15.00'],
    ]);

    return [$user, $contract];
}
function livePaymentData(string $amount): array
{
    return ['amount' => $amount, 'method' => 'PIX', 'paid_at' => now()->toIso8601String()];
}

function livePaymentWithDiscount(string $amount, string $discount): array
{
    return [...livePaymentData($amount), 'discount_amount' => $discount];
}

it('uses current accrued totals minus every valid contract payment with exact cents', function () {
    [$user,$contract] = liveReceivableFixture();
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentData('100.00'))
        ->assertOk()->assertJsonPath('data.total_accrued', '300.00')->assertJsonPath('data.total_paid', '100.00')->assertJsonPath('data.balance', '200.00');
    $this->getJson("/api/v1/contracts/{$contract->id}")->assertJsonPath('data.balance', '200.00')->assertJsonPath('data.rental_total', '270.00')->assertJsonPath('data.freight_total', '30.00');
    expect(Charge::count())->toBe(0)->and(Payment::first()->contract_id)->toBe($contract->id)->and(Payment::first()->charge_id)->toBeNull();
});

it('settles balances with payments and discounts while keeping revenue separate', function () {
    [$user, $contract] = liveReceivableFixture();

    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentWithDiscount('80.00', '20.00'))
        ->assertOk()
        ->assertJsonPath('data.total_paid', '80.00')
        ->assertJsonPath('data.total_discount', '20.00')
        ->assertJsonPath('data.balance', '200.00');

    $this->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentWithDiscount('50.00', '20.00'))
        ->assertOk()
        ->assertJsonPath('data.total_paid', '130.00')
        ->assertJsonPath('data.total_discount', '40.00')
        ->assertJsonPath('data.balance', '130.00');

    $finance = app(ContractFinanceService::class)->summarize($contract->fresh());
    expect($finance['total_paid'])->toBe('130.00');
    expect($finance['total_discount'])->toBe('40.00');
    expect($finance['balance'])->toBe('130.00');
});

it('rejects payment plus discount above the current balance', function () {
    [$user, $contract] = liveReceivableFixture();

    $this->actingAs($user)
        ->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentWithDiscount('290.00', '20.00'))
        ->assertUnprocessable();

    expect(Payment::count())->toBe(0);
});

it('allows full discount settlement without received revenue', function () {
    [$user, $contract] = liveReceivableFixture();

    $this->actingAs($user)
        ->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentWithDiscount('0.00', '300.00'))
        ->assertOk()
        ->assertJsonPath('data.total_paid', '0.00')
        ->assertJsonPath('data.total_discount', '300.00')
        ->assertJsonPath('data.balance', '0.00');

    $this->get('/dashboard')
        ->assertInertia(fn (Assert $p) => $p->where('metrics.received', '0.00')->where('metrics.balance', '0.00'));
});

it('advances charge cycle when payment and discount settle the full balance', function () {
    [$user, $contract] = liveReceivableFixture();

    $this->actingAs($user)
        ->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentWithDiscount('250.00', '50.00'))
        ->assertOk()
        ->assertJsonPath('data.balance', '0.00')
        ->assertJsonPath('data.next_charge_date', '2026-10-20');
});

it('marks returned contracts settled by discount as ready and finalizes them', function () {
    [$user, $contract] = liveReceivableFixture();
    app(CreateMovementAction::class)->handle($contract, ['type' => 'RETURN', 'occurred_at' => '2026-10-05 16:00', 'items' => [['contract_item_id' => $contract->items()->first()->id, 'quantity' => 1]]]);

    $this->actingAs($user)
        ->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentWithDiscount('250.00', '50.00'))
        ->assertOk()
        ->assertJsonPath('data.can_finalize', true)
        ->assertJsonPath('data.display_status_label', 'Pronto para finalizar');

    $this->postJson("/api/v1/contracts/{$contract->id}/finalize")
        ->assertOk()
        ->assertJsonPath('data.status', 'FINALIZED')
        ->assertJsonPath('data.financial_balance', '0.00');
});

it('returns payment discount fields through the api and excludes discounts from received summaries', function () {
    [$user, $contract] = liveReceivableFixture();

    $this->actingAs($user)
        ->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentWithDiscount('80.00', '20.00'))
        ->assertOk();

    $this->getJson('/api/v1/payments')
        ->assertOk()
        ->assertJsonPath('data.0.amount', '80.00')
        ->assertJsonPath('data.0.discount_amount', '20.00')
        ->assertJsonPath('data.0.settled_amount', '100.00')
        ->assertJsonPath('summary.today', '80.00')
        ->assertJsonPath('summary.month', '80.00')
        ->assertJsonPath('summary.filtered_discount', '20.00');

    $this->getJson("/api/v1/contracts/{$contract->id}")
        ->assertJsonPath('data.total_paid', '80.00')
        ->assertJsonPath('data.total_discount', '20.00')
        ->assertJsonPath('data.financial_balance', '200.00');
});

it('shows due contracts automatically without materializing or advancing cycles', function () {
    [$user,$contract] = liveReceivableFixture();
    $this->actingAs($user)->get('/charges?filter=today')->assertOk()->assertInertia(fn (Assert $p) => $p->has('charges.data', 1)->where('charges.data.0.balance', '300.00')->where('charges.data.0.due_today', true));
    foreach (range(1, 3) as $repeat) {
        $this->getJson('/api/v1/charges')->assertOk()->assertJsonCount(1, 'data');
    }
    expect(Charge::count())->toBe(0)->and($contract->fresh()->next_charge_date->toDateString())->toBe('2026-10-05');
});

it('shows future active contracts only under upcoming and keeps overdue filters accurate', function () {
    [$user,$contract] = liveReceivableFixture('2026-10-06');
    $this->actingAs($user)->getJson('/api/v1/charges')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/charges?filter=overdue')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/charges?filter=today')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/charges?filter=upcoming')->assertJsonCount(1, 'data')->assertJsonPath('data.0.days_overdue', 0);
});

it('shows returned contracts with a balance even before the next charge date', function () {
    [$user,$contract] = liveReceivableFixture('2026-10-20');
    app(CreateMovementAction::class)->handle($contract, ['type' => 'RETURN', 'occurred_at' => '2026-10-05 16:00', 'items' => [['contract_item_id' => $contract->items()->first()->id, 'quantity' => 1]]]);
    $this->actingAs($user)->getJson('/api/v1/charges')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_collectible', true);
});

it('keeps partial payments overdue without changing the schedule and recalculates days dynamically', function () {
    [$user,$contract] = liveReceivableFixture('2026-10-01');
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentData('80.00'))
        ->assertOk()->assertJsonPath('data.balance', '220.00')->assertJsonPath('data.days_overdue', 4)->assertJsonPath('data.next_charge_date', '2026-10-01')->assertJsonPath('data.financial_status', 'PARTIAL');
    $this->getJson('/api/v1/charges?filter=overdue')->assertJsonCount(1, 'data');
    $this->travelTo(CarbonImmutable::parse('2026-10-08 18:00', 'America/Belem'));
    $this->getJson("/api/v1/charges/{$contract->id}")->assertJsonPath('data.days_overdue', 7)->assertJsonPath('data.next_charge_date', '2026-10-01');
});

it('advances the existing schedule only when all of the current balance is paid', function () {
    [$user,$contract] = liveReceivableFixture();
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentData('80.00'))->assertOk()->assertJsonPath('data.next_charge_date', '2026-10-05');
    $this->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentData('220.00'))->assertOk()->assertJsonPath('data.total_paid', '300.00')->assertJsonPath('data.balance', '0.00')->assertJsonPath('data.next_charge_date', '2026-10-20');
    $this->getJson('/api/v1/charges')->assertJsonCount(0, 'data');
    $this->travelTo(CarbonImmutable::parse('2026-10-06 18:00', 'America/Belem'));
    $this->getJson('/api/v1/charges')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/charges?filter=upcoming')->assertJsonCount(1, 'data')->assertJsonPath('data.0.balance', '270.00');
});

it('repeatedly advances an old date by its interval until it is after today', function () {
    [$user,$contract] = liveReceivableFixture('2026-09-01');
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentData('300.00'))->assertOk()->assertJsonPath('data.next_charge_date', '2026-10-16');
});

it('ignores charge snapshots when calculating real balances and preserves the historical snapshot', function () {
    [$user,$contract] = liveReceivableFixture();
    $charge = app(CreateChargeAction::class)->handle($contract, []);
    Freight::factory()->for($contract)->create(['company_id' => $user->company_id, 'quantity' => 1, 'unit_amount' => '100.00', 'occurred_at' => now()]);
    $this->actingAs($user)->getJson('/api/v1/charges')->assertJsonPath('data.0.total_accrued', '400.00')->assertJsonPath('data.0.balance', '400.00');
    $this->postJson("/api/v1/charges/history/{$charge->id}/payments", livePaymentData('400.00'))->assertOk()->assertJsonPath('data.balance', '0.00');
    expect($charge->fresh()->total_amount)->toBe('300.00');
    $this->getJson("/api/v1/charges/history/{$charge->id}")->assertOk()->assertJsonPath('data.total_amount', '300.00')->assertJsonPath('data.contract_finance.total_paid', '400.00');
    $charge->update(['status' => 'CANCELLED']);
    expect(app(ContractFinanceService::class)->summarize($contract->fresh())['total_paid'])->toBe('400.00');
    expect(fn () => $charge->update(['total_amount' => '1.00']))->toThrow(ValidationException::class);
});

it('only finalizes returned physical quantities when the current balance is zero', function () {
    [$user,$contract] = liveReceivableFixture();
    $this->actingAs($user)->patchJson("/api/v1/contracts/{$contract->id}", ['status' => 'FINALIZED'])->assertUnprocessable();
    $this->postJson("/api/v1/contracts/{$contract->id}/movements", ['type' => 'RETURN', 'occurred_at' => '2026-10-05T16:00', 'items' => [['contract_item_id' => $contract->items()->first()->id, 'quantity' => 1]]])->assertCreated();
    $this->patchJson("/api/v1/contracts/{$contract->id}", ['status' => 'FINALIZED'])->assertUnprocessable();
    $this->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentData('300.00'))->assertOk();
    $this->patchJson("/api/v1/contracts/{$contract->id}", ['status' => 'FINALIZED'])->assertOk();
    $this->getJson('/api/v1/charges?filter=upcoming')->assertJsonCount(0, 'data');
});

it('excludes cancelled contracts and blocks payments above the current balance', function () {
    [$user,$contract] = liveReceivableFixture();
    $this->actingAs($user)->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentData('300.01'))->assertUnprocessable();
    $contract->update(['status' => 'CANCELLED']);
    $this->getJson('/api/v1/charges')->assertJsonCount(0, 'data');
    $this->postJson("/api/v1/contracts/{$contract->id}/payments", livePaymentData('1.00'))->assertUnprocessable();
    expect(Payment::count())->toBe(0);
});

it('isolates receivables and contract payments by company', function () {
    [$user,$contract] = liveReceivableFixture();
    [$other,$foreign] = liveReceivableFixture();
    $this->actingAs($user)->getJson('/api/v1/charges')->assertJsonCount(1, 'data')->assertJsonPath('data.0.contract_id', $contract->id);
    $this->getJson("/api/v1/charges/{$foreign->id}")->assertNotFound();
    $this->postJson("/api/v1/contracts/{$foreign->id}/payments", livePaymentData('1.00'))->assertNotFound();
    $this->postJson("/api/v1/contracts/{$contract->id}/payments", [...livePaymentData('100.00'), 'company_id' => $other->company_id, 'contract_id' => $foreign->id])->assertOk();
    expect(Payment::first()->company_id)->toBe($user->company_id)->and(Payment::first()->contract_id)->toBe($contract->id);
    $this->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('metrics.balance', '200.00')->where('metrics.received', '100.00')->has('recentContracts', 1));
});

it('preserves and backfills payments from the previous charge-only schema', function () {
    [$user,$contract] = liveReceivableFixture();
    $charge = app(CreateChargeAction::class)->handle($contract, []);
    $migration = require database_path('migrations/2026_10_05_210000_link_payments_to_contracts.php');
    $migration->down();
    $id = DB::table('payments')->insertGetId(['company_id' => $user->company_id, 'charge_id' => $charge->id, 'amount' => '100.00', 'paid_at' => now(), 'method' => 'PIX', 'notes' => 'Preserved payment', 'created_at' => now(), 'updated_at' => now()]);
    $migration->up();
    expect(Payment::find($id)->contract_id)->toBe($contract->id)->and(Payment::find($id)->amount)->toBe('100.00')->and(Payment::find($id)->charge_id)->toBe($charge->id);
    expect(app(ContractFinanceService::class)->summarize($contract->fresh())['balance'])->toBe('200.00');
});

it('registers payments from the web detail and supplies actual dashboard totals', function () {
    [$user,$contract] = liveReceivableFixture('2026-10-01');
    $this->actingAs($user)->get('/charges')->assertOk()->assertInertia(fn (Assert $p) => $p->component('charges/Index')->has('charges.data', 1));
    $this->get("/charges/{$contract->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('charges/Show')->where('charge.balance', '300.00'));
    $this->post("/contracts/{$contract->id}/payments", livePaymentData('100.00'))->assertRedirect();
    $this->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('metrics.overdue', 1)->where('metrics.balance', '200.00')->where('metrics.received', '100.00')->has('attention', 1));
});

it('accepts the mobile receivables payment endpoint without requiring any charge record', function () {
    [$user,$contract] = liveReceivableFixture();
    $this->actingAs($user)->postJson("/api/v1/charges/{$contract->id}/payments", livePaymentData('100.00'))
        ->assertOk()->assertJsonPath('data.total_paid', '100.00')->assertJsonPath('data.balance', '200.00');
    expect(Charge::count())->toBe(0)->and(Payment::first()->contract_id)->toBe($contract->id);
});

it('refuses a destructive rollback when direct contract payments already exist', function () {
    [$user,$contract] = liveReceivableFixture();
    app(RegisterContractPaymentAction::class)->handle($contract, livePaymentData('100.00'));
    $migration = require database_path('migrations/2026_10_05_210000_link_payments_to_contracts.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect(Payment::first()->contract_id)->toBe($contract->id)->and(Payment::first()->amount)->toBe('100.00');
});
