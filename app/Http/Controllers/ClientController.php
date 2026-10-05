<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
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

        $clients = Client::query()
            ->whereBelongsTo($company)
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
    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = $this->userCompany($request)
            ->clients()
            ->create($request->validated());

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
    public function update(UpdateClientRequest $request, int $client): RedirectResponse
    {
        $client = $this->findClientForUser($request, $client);

        Gate::authorize('update', $client);

        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente Atualizado com Sucesso.')]);

        return to_route('clients.show', $client);
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
