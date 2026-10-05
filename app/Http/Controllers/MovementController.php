<?php

namespace App\Http\Controllers;

use App\Actions\Movements\CreateMovementAction;
use App\Actions\Movements\UpdateMovementAction;
use App\Enums\MovementType;
use App\Http\Requests\Movements\StoreMovementRequest;
use App\Http\Requests\Movements\UpdateMovementRequest;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Movement;
use App\Services\MovementBalanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MovementController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Movement::class);

        $company = $this->userCompany($request);
        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->trim()->toString();

        $movements = Movement::query()
            ->with(['contract.client', 'items.contractItem.product'])
            ->whereBelongsTo($company)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('contract', function (Builder $query) use ($search): void {
                    $query
                        ->where('id', $search)
                        ->orWhereHas('client', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($type !== '', fn (Builder $query) => $query->where('type', $type))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Movement $movement): array => $this->movementData($movement));

        return Inertia::render('movements/Index', [
            'movements' => $movements,
            'filters' => ['search' => $search, 'type' => $type],
            'movementTypes' => $this->movementTypes(),
        ]);
    }

    public function create(Request $request, MovementBalanceService $balances): Response
    {
        Gate::authorize('create', Movement::class);

        $company = $this->userCompany($request);
        $contract = $this->selectedContract($request, $company);

        return Inertia::render('movements/Create', [
            'contracts' => $this->contractOptions($company),
            'selectedContract' => $contract !== null ? $this->contractMovementOptions($contract, $balances) : null,
            'selectedType' => $request->string('type')->trim()->toString() ?: MovementType::Withdrawal->value,
            'movementTypes' => $this->movementTypes(),
        ]);
    }

    public function store(StoreMovementRequest $request, CreateMovementAction $createMovement): RedirectResponse
    {
        $contract = Contract::query()
            ->with(['company', 'client', 'items.product', 'items.movementItems.movement', 'movements.items'])
            ->whereBelongsTo($this->userCompany($request))
            ->findOrFail($request->integer('contract_id'));

        $movement = $createMovement->handle($contract, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Movimentação Criada com Sucesso.')]);

        return to_route('movements.show', $movement);
    }

    public function show(Request $request, Movement $movement): Response
    {
        $movement = $this->findMovementForUser($request, $movement);
        Gate::authorize('view', $movement);

        return Inertia::render('movements/Show', [
            'movement' => $this->movementData($movement),
        ]);
    }

    public function edit(Request $request, Movement $movement, MovementBalanceService $balances): Response
    {
        $movement = $this->findMovementForUser($request, $movement);
        Gate::authorize('update', $movement);

        return Inertia::render('movements/Edit', [
            'movement' => $this->movementData($movement),
            'selectedContract' => $this->contractMovementOptions($movement->contract, $balances),
        ]);
    }

    public function update(UpdateMovementRequest $request, Movement $movement, UpdateMovementAction $updateMovement): RedirectResponse
    {
        $movement = $this->findMovementForUser($request, $movement);
        Gate::authorize('update', $movement);

        $movement = $updateMovement->handle($movement, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Movimentação Atualizada com Sucesso.')]);

        return to_route('movements.show', $movement);
    }

    public function destroy(): never
    {
        abort(404);
    }

    private function findMovementForUser(Request $request, Movement $movement): Movement
    {
        return Movement::query()
            ->with(['contract.company', 'contract.client', 'contract.items.product', 'contract.items.movementItems.movement', 'items.contractItem.product'])
            ->whereBelongsTo($this->userCompany($request))
            ->findOrFail($movement->id);
    }

    private function selectedContract(Request $request, Company $company): ?Contract
    {
        $contractId = $request->integer('contract');

        if ($contractId === 0) {
            return null;
        }

        return Contract::query()
            ->with(['client', 'items.product', 'items.movementItems.movement'])
            ->whereBelongsTo($company)
            ->findOrFail($contractId);
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;
        abort_if($company === null, 403);

        return $company;
    }

    /**
     * @return array<string, mixed>
     */
    private function movementData(Movement $movement): array
    {
        $movement->loadMissing(['contract.client', 'items.contractItem.product']);

        return [
            'id' => $movement->id,
            'type' => $movement->type->value,
            'type_label' => $this->movementTypeLabel($movement->type),
            'occurred_at' => $movement->occurred_at?->format('Y-m-d\TH:i'),
            'notes' => $movement->notes,
            'contract' => [
                'id' => $movement->contract->id,
                'number' => $movement->contract->id,
                'client' => [
                    'id' => $movement->contract->client->id,
                    'name' => $movement->contract->client->name,
                ],
            ],
            'items' => $movement->items->map(fn ($item): array => [
                'id' => $item->id,
                'contract_item_id' => $item->contract_item_id,
                'quantity' => $item->quantity,
                'product' => [
                    'id' => $item->contractItem->product->id,
                    'name' => $item->contractItem->product->name,
                ],
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contractMovementOptions(Contract $contract, MovementBalanceService $balances): array
    {
        $quantities = $balances->currentQuantities($contract);

        return [
            'id' => $contract->id,
            'number' => $contract->id,
            'client' => ['id' => $contract->client->id, 'name' => $contract->client->name],
            'items' => $contract->items->map(fn ($item): array => [
                'id' => $item->id,
                'product' => ['id' => $item->product->id, 'name' => $item->product->name, 'type' => $item->product->type->value],
                'current_quantity' => (int) ($quantities->get($item->id) ?? 0),
            ])->all(),
        ];
    }

    private function contractOptions(Company $company): array
    {
        return Contract::query()
            ->with('client')
            ->whereBelongsTo($company)
            ->latest('started_at')
            ->limit(100)
            ->get()
            ->map(fn (Contract $contract): array => [
                'id' => $contract->id,
                'number' => $contract->id,
                'client_name' => $contract->client->name,
            ])
            ->all();
    }

    private function movementTypes(): array
    {
        return collect(MovementType::cases())
            ->map(fn (MovementType $type): array => ['value' => $type->value, 'label' => $this->movementTypeLabel($type)])
            ->all();
    }

    private function movementTypeLabel(MovementType $type): string
    {
        return match ($type) {
            MovementType::Withdrawal => 'Retirada',
            MovementType::Return => 'Devolução',
        };
    }
}
