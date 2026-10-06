<?php

use App\Actions\Contracts\CreateContractAction;
use App\Enums\ContractStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function attachmentFixture(): array
{
    config(['filesystems.contract_attachments_disk' => 'local']);
    Storage::fake('local');

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $contract = app(CreateContractAction::class)->handle($company, [
        'client_id' => $client->id,
        'started_at' => '2026-10-05T09:00',
        'charge_saturdays' => true,
        'items' => [['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00']],
    ]);

    return [$user, $contract];
}

it('uploads a valid contract image', function () {
    [$user, $contract] = attachmentFixture();

    $this
        ->actingAs($user)
        ->post(route('contracts.attachments.store', $contract), [
            'attachments' => [UploadedFile::fake()->image('obra.jpg')],
        ])
        ->assertRedirect();

    $attachment = ContractAttachment::query()->firstOrFail();

    expect($attachment->company_id)->toBe($user->company_id);
    expect($attachment->contract_id)->toBe($contract->id);
    expect($attachment->uploaded_by)->toBe($user->id);
    Storage::disk('local')->assertExists($attachment->file_path);
});

it('creates a contract and uploads an attachment with the official multipart field', function () {
    config(['filesystems.contract_attachments_disk' => 'local']);
    Storage::fake('local');

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $client = Client::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();

    $createResponse = $this
        ->actingAs($user)
        ->postJson(route('contracts.store'), [
            'client_id' => $client->id,
            'started_at' => '2026-10-05T09:00',
            'charge_saturdays' => true,
            'items' => [
                ['product_id' => $product->id, 'billing_period' => 'DAY', 'unit_price' => '10.00'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('contract.id', fn (int $id): bool => $id > 0);

    $contract = Contract::query()->findOrFail($createResponse->json('contract.id'));

    $this
        ->postJson(route('contracts.attachments.store', $contract), [
            'attachments' => [UploadedFile::fake()->image('obra.jpg')],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $attachment = ContractAttachment::query()->firstOrFail();

    expect($attachment->company_id)->toBe($company->id);
    expect($attachment->contract_id)->toBe($contract->id);
    Storage::disk('local')->assertExists($attachment->file_path);
});

it('uploads multiple images up to the contract limit', function () {
    [$user, $contract] = attachmentFixture();

    $this
        ->actingAs($user)
        ->post(route('contracts.attachments.store', $contract), [
            'attachments' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.png'),
            ],
        ])
        ->assertRedirect();

    expect($contract->attachments()->count())->toBe(2);
});

it('rejects more than ten images for one contract', function () {
    [$user, $contract] = attachmentFixture();

    $this
        ->actingAs($user)
        ->post(route('contracts.attachments.store', $contract), [
            'attachments' => collect(range(1, 11))->map(fn (int $i) => UploadedFile::fake()->image("{$i}.jpg"))->all(),
        ])
        ->assertSessionHasErrors('attachments');

    expect($contract->attachments()->count())->toBe(0);
});

it('rejects non images and files over ten megabytes', function () {
    [$user, $contract] = attachmentFixture();

    $this
        ->actingAs($user)
        ->post(route('contracts.attachments.store', $contract), [
            'attachments' => [UploadedFile::fake()->create('arquivo.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasErrors('attachments.0');

    $this
        ->post(route('contracts.attachments.store', $contract), [
            'attachments' => [UploadedFile::fake()->image('grande.jpg')->size(10241)],
        ])
        ->assertSessionHasErrors('attachments.0');
});

it('allows same company users to view and blocks other companies', function () {
    [$user, $contract] = attachmentFixture();
    $this->actingAs($user)->post(route('contracts.attachments.store', $contract), [
        'attachments' => [UploadedFile::fake()->image('obra.jpg')],
    ]);
    $attachment = ContractAttachment::query()->firstOrFail();
    $other = User::factory()->for(Company::factory())->create();

    $this->actingAs($user)->get(route('contracts.attachments.show', [$contract, $attachment]))->assertOk();
    $this->actingAs($other)->get(route('contracts.attachments.show', [$contract, $attachment]))->assertNotFound();
});

it('removes an attachment and deletes the storage file', function () {
    [$user, $contract] = attachmentFixture();
    $this->actingAs($user)->post(route('contracts.attachments.store', $contract), [
        'attachments' => [UploadedFile::fake()->image('obra.jpg')],
    ]);
    $attachment = ContractAttachment::query()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->file_path);

    $this
        ->delete(route('contracts.attachments.destroy', [$contract, $attachment]))
        ->assertRedirect();

    Storage::disk('local')->assertMissing($attachment->file_path);
    expect(ContractAttachment::query()->whereKey($attachment->id)->exists())->toBeFalse();
});

it('keeps a created contract when a later upload fails validation', function () {
    [$user, $contract] = attachmentFixture();

    $this
        ->actingAs($user)
        ->post(route('contracts.attachments.store', $contract), [
            'attachments' => [UploadedFile::fake()->create('arquivo.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasErrors('attachments.0');

    expect(Contract::query()->whereKey($contract->id)->exists())->toBeTrue();
});

it('returns attachment metadata in contract resource details', function () {
    [$user, $contract] = attachmentFixture();
    $this->actingAs($user)->post(route('contracts.attachments.store', $contract), [
        'attachments' => [UploadedFile::fake()->image('obra.jpg')],
    ]);

    $this
        ->getJson("/api/v1/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('data.attachments_count', 1)
        ->assertJsonPath('data.attachments.0.original_name', 'obra.jpg')
        ->assertJsonMissingPath('data.attachments.0.file_path');
});

it('supports api upload and tenant isolation', function () {
    [$user, $contract] = attachmentFixture();
    $other = User::factory()->for(Company::factory())->create();

    $this
        ->actingAs($user)
        ->postJson("/api/v1/contracts/{$contract->id}/attachments", [
            'attachments' => [UploadedFile::fake()->image('api.webp')],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $attachment = ContractAttachment::query()->firstOrFail();

    $this->actingAs($other)->getJson("/api/v1/contracts/{$contract->id}/attachments/{$attachment->id}")->assertNotFound();
});

it('blocks uploading and removing attachments on finalized contracts', function () {
    [$user, $contract] = attachmentFixture();
    $this->actingAs($user)->post(route('contracts.attachments.store', $contract), [
        'attachments' => [UploadedFile::fake()->image('obra.jpg')],
    ]);
    $attachment = ContractAttachment::query()->firstOrFail();
    $contract->update(['status' => ContractStatus::Finalized]);

    $this->post(route('contracts.attachments.store', $contract), [
        'attachments' => [UploadedFile::fake()->image('nova.jpg')],
    ])->assertForbidden();

    $this->delete(route('contracts.attachments.destroy', [$contract, $attachment]))->assertForbidden();
});
