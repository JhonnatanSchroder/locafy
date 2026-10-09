<?php

use App\Actions\Contracts\CreateContractAction;
use App\Enums\BillingPeriod;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;

function apiPaymentFixture(): array
{
    test()->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'America/Belem'));

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create([
        'name' => 'João Silva',
        'phone' => '(91) 99999-0000',
        'document' => '12345678900',
    ]);
    $product = Product::factory()->for($company)->create(['name' => 'Andaime']);
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-08T08:00',
        'charge_saturdays' => true,
        'next_charge_date' => '2026-10-09',
        'items' => [
            ['product_id' => $product->id, 'billing_period' => BillingPeriod::Day->value, 'unit_price' => '100.00', 'initial_quantity' => 1],
        ],
    ]);

    return [$company, $user, $client, $contract];
}

it('lists only company payments ordered by most recent with api payload totals', function () {
    [$company, $user, $client, $contract] = apiPaymentFixture();
    $older = Payment::query()->create([
        'company_id' => $company->id,
        'contract_id' => $contract->id,
        'amount' => '50.00',
        'discount_amount' => '0.00',
        'paid_at' => '2026-10-08 10:00:00',
        'method' => 'CASH',
    ]);
    $newer = Payment::query()->create([
        'company_id' => $company->id,
        'contract_id' => $contract->id,
        'amount' => '80.00',
        'discount_amount' => '20.00',
        'paid_at' => '2026-10-09 10:00:00',
        'method' => 'PIX',
        'notes' => 'Parcial',
    ]);
    [$otherCompany, , , $otherContract] = apiPaymentFixture();
    Payment::query()->create([
        'company_id' => $otherCompany->id,
        'contract_id' => $otherContract->id,
        'amount' => '10.00',
        'discount_amount' => '0.00',
        'paid_at' => '2026-10-10 10:00:00',
        'method' => 'PIX',
    ]);

    $this
        ->withToken($user->createToken('Mobile')->plainTextToken)
        ->getJson('/api/v1/payments')
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta', 'summary'])
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.amount', '80.00')
        ->assertJsonPath('data.0.discount_amount', '20.00')
        ->assertJsonPath('data.0.settled_amount', '100.00')
        ->assertJsonPath('data.0.method', 'PIX')
        ->assertJsonPath('data.0.contract.id', $contract->id)
        ->assertJsonPath('data.0.contract.number', $contract->id)
        ->assertJsonPath('data.0.client.id', $client->id)
        ->assertJsonPath('data.0.client.name', 'João Silva')
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('summary.filtered_total', '130.00')
        ->assertJsonPath('summary.filtered_discount', '20.00');
});

it('filters payments by search contract client date and method with pagination', function () {
    [$company, $user, $client, $contract] = apiPaymentFixture();
    Payment::query()->create([
        'company_id' => $company->id,
        'contract_id' => $contract->id,
        'amount' => '10.00',
        'discount_amount' => '0.00',
        'paid_at' => '2026-10-08 10:00:00',
        'method' => 'CASH',
    ]);
    $match = Payment::query()->create([
        'company_id' => $company->id,
        'contract_id' => $contract->id,
        'amount' => '20.00',
        'discount_amount' => '5.00',
        'paid_at' => '2026-10-09 10:00:00',
        'method' => 'PIX',
    ]);
    $token = $user->createToken('Mobile')->plainTextToken;
    $base = "/api/v1/payments?payment_method=PIX&contract_id={$contract->id}&client_id={$client->id}&date_from=2026-10-09&date_to=2026-10-09&per_page=1";

    $this->withToken($token)->getJson($base)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id)->assertJsonPath('meta.per_page', 1);
    $this->withToken($token)->getJson($base.'&search=João')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    $this->withToken($token)->getJson($base.'&search=99999')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    $this->withToken($token)->getJson($base.'&search='.$contract->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
});

it('shows payment details and isolates by company', function () {
    [$company, $user, , $contract] = apiPaymentFixture();
    $payment = Payment::query()->create([
        'company_id' => $company->id,
        'contract_id' => $contract->id,
        'amount' => '30.00',
        'discount_amount' => '7.00',
        'paid_at' => '2026-10-09 10:00:00',
        'method' => 'TRANSFER',
    ]);
    [$foreignCompany, , , $foreignContract] = apiPaymentFixture();
    $foreignPayment = Payment::query()->create([
        'company_id' => $foreignCompany->id,
        'contract_id' => $foreignContract->id,
        'amount' => '1.00',
        'discount_amount' => '0.00',
        'paid_at' => '2026-10-09 10:00:00',
        'method' => 'PIX',
    ]);
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson("/api/v1/payments/{$payment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $payment->id)
        ->assertJsonPath('data.settled_amount', '37.00')
        ->assertJsonPath('data.contract.id', $contract->id);

    $this
        ->withToken($token)
        ->getJson("/api/v1/payments/{$foreignPayment->id}")
        ->assertNotFound();
});

it('keeps existing contract payment creation endpoint and financial validations', function () {
    [$company, $user, , $contract] = apiPaymentFixture();
    $token = $user->createToken('Mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson("/api/v1/contracts/{$contract->id}/payments", [
            'amount' => '0.00',
            'discount_amount' => '100.00',
            'paid_at' => '2026-10-09T10:00:00',
            'method' => 'PIX',
        ])
        ->assertOk();

    expect(Payment::query()->where('company_id', $company->id)->first()?->amount)->toBe('0.00');

    $this
        ->withToken($token)
        ->postJson("/api/v1/contracts/{$contract->id}/payments", [
            'amount' => '999999.99',
            'discount_amount' => '0.00',
            'paid_at' => '2026-10-09T10:00:00',
            'method' => 'PIX',
        ])
        ->assertUnprocessable();
});
