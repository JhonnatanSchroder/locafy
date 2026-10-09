<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Movements\UpdateMovementAction;
use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Movements\UpdateMovementRequest;
use App\Http\Resources\MovementResource;
use App\Models\Company;
use App\Models\Movement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MovementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Movement::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(MovementType::class)],
            'contract_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        return MovementResource::collection(
            Movement::query()
                ->with($this->relations())
                ->whereBelongsTo($this->userCompany($request))
                ->when(filled($validated['type'] ?? null), fn (Builder $query): Builder => $query->where('type', $validated['type']))
                ->when(filled($validated['contract_id'] ?? null), fn (Builder $query): Builder => $query->where('contract_id', $validated['contract_id']))
                ->when(filled($validated['client_id'] ?? null), function (Builder $query) use ($validated): void {
                    $query->whereHas('contract', fn (Builder $query): Builder => $query->where('client_id', $validated['client_id']));
                })
                ->when(filled($validated['date_from'] ?? null), fn (Builder $query): Builder => $query->where('occurred_at', '>=', $validated['date_from']))
                ->when(filled($validated['date_to'] ?? null), fn (Builder $query): Builder => $query->where('occurred_at', '<=', CarbonImmutable::parse($validated['date_to'])->endOfDay()))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query
                            ->where('contract_id', $search)
                            ->orWhereHas('contract.client', function (Builder $query) use ($search): void {
                                $query
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('document', 'like', "%{$search}%");
                            });
                    });
                })
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->paginate($perPage)
        );
    }

    public function show(Request $request, Movement $movement): MovementResource
    {
        $movement = $this->movementForUser($request, $movement);
        Gate::authorize('view', $movement);

        return new MovementResource($movement);
    }

    public function update(UpdateMovementRequest $request, Movement $movement, UpdateMovementAction $action): MovementResource
    {
        $movement = $this->movementForUser($request, $movement);
        Gate::authorize('update', $movement);

        return new MovementResource($action->handle($movement, $request->validated())->load($this->relations()));
    }

    private function movementForUser(Request $request, Movement $movement): Movement
    {
        return Movement::query()
            ->with($this->relations())
            ->whereBelongsTo($this->userCompany($request))
            ->findOrFail($movement->id);
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;

        abort_if($company === null, 403);

        return $company;
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return ['contract.client', 'items.contractItem.product', 'items.equipment'];
    }
}
