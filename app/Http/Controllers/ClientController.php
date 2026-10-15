<?php

namespace App\Http\Controllers;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Actions\Clients\ChangeClientStatusAction;
use App\Actions\Clients\CreateClientAction;
use App\Actions\Clients\DeleteClientAction;
use App\Actions\Clients\RestoreClientAction;
use App\Actions\Clients\UpdateClientAction;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Display a listing of the clients.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Client::class);

        $company = $this->userCompany($request);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $archived = $request->boolean('archived') && $request->user()->can('manageStatus', Client::factory()->make(['company_id' => $company->id]));

        $clients = Client::query()
            ->whereBelongsTo($company)
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->when(! $archived, fn ($query) => $query->withoutTrashed())
            ->when(in_array($status, array_column(ClientStatus::cases(), 'value'), true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $client): array => $this->clientData($client));

        return Inertia::render('clients/Index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'archived' => $archived,
            ],
            'clientTypes' => $this->clientTypes(),
        ]);
    }

    /**
     * Show the form for creating a new client.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Client::class);
        $this->userCompany($request);

        return Inertia::render('clients/Create', [
            'clientTypes' => $this->clientTypes(),
        ]);
    }

    /**
     * Store a newly created client in storage.
     */
    public function store(StoreClientRequest $request, CreateClientAction $createClient): RedirectResponse|JsonResponse
    {
        $client = $createClient->handle($this->userCompany($request), $request->validated());
        if ($request->expectsJson()) {
            return (new ClientResource($client))->response()->setStatusCode(201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente Criado com Sucesso.')]);

        return to_route('clients.show', $client);
    }

    /**
     * Display the specified client.
     */
    public function show(Request $request, int $client): Response
    {
        $client = $this->findClientForUser($request, $client);

        Gate::authorize('view', $client);

        return Inertia::render('clients/Show', [
            'client' => $this->clientData($client),
        ]);
    }

    /**
     * Show the form for editing the specified client.
     */
    public function edit(Request $request, int $client): Response
    {
        $client = $this->findClientForUser($request, $client);

        Gate::authorize('update', $client);

        return Inertia::render('clients/Edit', [
            'client' => $this->clientData($client),
            'clientTypes' => $this->clientTypes(),
        ]);
    }

    /**
     * Update the specified client in storage.
     */
    public function update(UpdateClientRequest $request, int $client, UpdateClientAction $updateClient): RedirectResponse
    {
        $client = $this->findClientForUser($request, $client);

        Gate::authorize('update', $client);

        $client = $updateClient->handle($client, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente Atualizado com Sucesso.')]);

        return to_route('clients.show', $client);
    }

    public function status(Request $request, int $client, ChangeClientStatusAction $change, RecordAdminAuditAction $audit): RedirectResponse
    {
        $model = $this->findClientForUser($request, $client);
        Gate::authorize('manageStatus', $model);
        $data = $request->validate(['status' => ['required', 'in:ACTIVE,BLOCKED,INACTIVE'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $change->handle($model, ClientStatus::from($data['status']), $request->user(), $data['reason'] ?? null, $audit);

        return back();
    }

    public function destroy(Request $request, int $client, DeleteClientAction $delete, RecordAdminAuditAction $audit): RedirectResponse
    {
        $model = Client::query()->withTrashed()->whereBelongsTo($this->userCompany($request))->findOrFail($client);
        Gate::authorize('delete', $model);
        $delete->handle($model, $request->user(), $audit);

        return to_route('clients.index');
    }

    public function restore(Request $request, int $client, RestoreClientAction $restore, RecordAdminAuditAction $audit): RedirectResponse
    {
        $model = Client::query()->withTrashed()->whereBelongsTo($this->userCompany($request))->findOrFail($client);
        Gate::authorize('restore', $model);
        $restore->handle($model, $request->user(), $audit);

        return back();
    }

    private function findClientForUser(Request $request, int $client): Client
    {
        return Client::query()
            ->whereBelongsTo($this->userCompany($request))
            ->findOrFail($client);
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;

        abort_if($company === null, 403);

        return $company;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function clientTypes(): array
    {
        return collect(ClientType::cases())
            ->map(fn (ClientType $type): array => [
                'value' => $type->value,
                'label' => $this->clientTypeLabel($type),
            ])
            ->all();
    }

    /**
     * @return array{id: int, type: string, type_label: string, name: string, document: string|null, phone: string|null, residential_address: string|null, notes: string|null}
     */
    private function clientData(Client $client): array
    {
        return [
            'id' => $client->id,
            'type' => $client->type->value,
            'type_label' => $this->clientTypeLabel($client->type),
            'name' => $client->name,
            'document' => $client->document,
            'phone' => $client->phone,
            'residential_address' => $client->residential_address,
            'notes' => $client->notes,
            'status' => $client->status?->value ?? 'ACTIVE',
            'status_reason' => $client->status_reason,
            'status_changed_at' => $client->status_changed_at?->toISOString(),
        ];
    }

    private function clientTypeLabel(ClientType $type): string
    {
        return match ($type) {
            ClientType::Individual => 'Pessoa Física',
            ClientType::Company => 'Pessoa Jurídica',
        };
    }
}
