<?php

namespace App\Http\Controllers;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Enums\EquipmentStatus;
use App\Enums\ProductType;
use App\Http\Requests\Equipments\StoreEquipmentRequest;
use App\Http\Requests\Equipments\UpdateEquipmentRequest;
use App\Models\Company;
use App\Models\Equipment;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentController extends Controller
{
    public function toggleStatus(Request $request, int $equipment, RecordAdminAuditAction $audit): RedirectResponse
    {
        $equipment = $this->findEquipmentForUser($request, $equipment);

        Gate::authorize('update', $equipment);
        abort_unless($request->user()?->canAdministrateCompany(), 403);

        if ($equipment->status === EquipmentStatus::Rented && $equipment->status !== EquipmentStatus::Inactive) {
            abort(422, 'Equipamento alugado não pode ser desativado.');
        }

        $before = ['status' => $equipment->status->value];
        $nextStatus = $equipment->status === EquipmentStatus::Inactive
            ? EquipmentStatus::Available
            : EquipmentStatus::Inactive;
        $equipment->update(['status' => $nextStatus]);

        $audit->handle($request->user(), 'equipment', $equipment->id, $nextStatus === EquipmentStatus::Inactive ? 'deactivated' : 'activated', $before, ['status' => $nextStatus->value]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $nextStatus === EquipmentStatus::Inactive ? __('Equipamento desativado com sucesso.') : __('Equipamento reativado com sucesso.')]);

        return back();
    }

    /**
     * Display a listing of the equipments.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Equipment::class);

        $company = $this->userCompany($request);
        $search = $request->string('search')->trim()->toString();
        $product = $request->string('product')->trim()->toString();
        $status = $request->string('status')->trim()->toString();

        $equipments = Equipment::query()
            ->with('product')
            ->whereBelongsTo($company)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            })
            ->when($product !== '', function (Builder $query) use ($product): void {
                $query->where('product_id', $product);
            })
            ->when($status !== '', function (Builder $query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Equipment $equipment): array => $this->equipmentData($equipment));

        return Inertia::render('equipments/Index', [
            'equipments' => $equipments,
            'filters' => [
                'search' => $search,
                'product' => $product,
                'status' => $status,
            ],
            'eligibleProducts' => $this->eligibleProducts($company),
            'equipmentStatuses' => $this->equipmentStatuses(),
        ]);
    }

    /**
     * Show the form for creating a new equipment.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Equipment::class);

        $company = $this->userCompany($request);

        return Inertia::render('equipments/Create', [
            'eligibleProducts' => $this->eligibleProducts($company),
            'equipmentStatuses' => $this->equipmentStatuses(),
        ]);
    }

    /**
     * Store a newly created equipment in storage.
     */
    public function store(StoreEquipmentRequest $request, RecordAdminAuditAction $audit): RedirectResponse
    {
        $equipment = $this->userCompany($request)
            ->equipments()
            ->create($request->validated());
        $audit->handle($request->user(), 'equipment', $equipment->id, 'created', null, ['name' => $equipment->name, 'product_id' => $equipment->product_id, 'status' => $equipment->status->value]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Equipamento Criado com Sucesso.')]);

        return to_route('equipments.show', $equipment);
    }

    /**
     * Display the specified equipment.
     */
    public function show(Request $request, int $equipment): Response
    {
        $equipment = $this->findEquipmentForUser($request, $equipment);

        Gate::authorize('view', $equipment);

        return Inertia::render('equipments/Show', [
            'equipment' => $this->equipmentData($equipment),
        ]);
    }

    /**
     * Show the form for editing the specified equipment.
     */
    public function edit(Request $request, int $equipment): Response
    {
        $equipment = $this->findEquipmentForUser($request, $equipment);

        Gate::authorize('update', $equipment);

        return Inertia::render('equipments/Edit', [
            'equipment' => $this->equipmentData($equipment),
            'eligibleProducts' => $this->eligibleProducts($equipment->company, $equipment->product),
            'equipmentStatuses' => $this->equipmentStatuses(),
        ]);
    }

    /**
     * Update the specified equipment in storage.
     */
    public function update(UpdateEquipmentRequest $request, int $equipment, RecordAdminAuditAction $audit): RedirectResponse
    {
        $equipment = $this->findEquipmentForUser($request, $equipment);

        Gate::authorize('update', $equipment);

        $before = ['name' => $equipment->name, 'brand' => $equipment->brand, 'notes' => $equipment->notes, 'status' => $equipment->status->value, 'product_id' => $equipment->product_id];
        $equipment->update($request->validated());
        $equipment->refresh();
        $after = ['name' => $equipment->name, 'brand' => $equipment->brand, 'notes' => $equipment->notes, 'status' => $equipment->status->value, 'product_id' => $equipment->product_id];
        $changedBefore = array_intersect_key($before, array_diff_assoc($before, $after));
        if ($changedBefore !== []) {
            $audit->handle($request->user(), 'equipment', $equipment->id, 'updated', $changedBefore, array_intersect_key($after, $changedBefore));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Equipamento Atualizado com Sucesso.')]);

        return to_route('equipments.show', $equipment);
    }

    private function findEquipmentForUser(Request $request, int $equipment): Equipment
    {
        return Equipment::query()
            ->with(['company', 'product'])
            ->whereBelongsTo($this->userCompany($request))
            ->findOrFail($equipment);
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;

        abort_if($company === null, 403);

        return $company;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function eligibleProducts(Company $company, ?Product $currentProduct = null): array
    {
        return Product::query()
            ->whereBelongsTo($company)
            ->where('type', ProductType::Individual->value)
            ->where(function (Builder $query) use ($currentProduct): void {
                $query->where('active', true);

                if ($currentProduct !== null) {
                    $query->orWhere('id', $currentProduct->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function equipmentStatuses(): array
    {
        return collect(EquipmentStatus::cases())
            ->map(fn (EquipmentStatus $status): array => [
                'value' => $status->value,
                'label' => $this->equipmentStatusLabel($status),
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string, brand: string|null, notes: string|null, status: string, status_label: string, product: array{id: int, name: string}}
     */
    private function equipmentData(Equipment $equipment): array
    {
        $equipment->loadMissing('product');

        return [
            'id' => $equipment->id,
            'name' => $equipment->name,
            'brand' => $equipment->brand,
            'notes' => $equipment->notes,
            'status' => $equipment->status->value,
            'status_label' => $this->equipmentStatusLabel($equipment->status),
            'product' => [
                'id' => $equipment->product->id,
                'name' => $equipment->product->name,
            ],
        ];
    }

    private function equipmentStatusLabel(EquipmentStatus $status): string
    {
        return match ($status) {
            EquipmentStatus::Available => 'Disponível',
            EquipmentStatus::Rented => 'Alugado',
            EquipmentStatus::Maintenance => 'Manutenção',
            EquipmentStatus::Inactive => 'Inativo',
        };
    }
}
