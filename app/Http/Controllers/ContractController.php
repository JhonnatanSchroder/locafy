<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\CreateContractAction;
use App\Actions\Contracts\UpdateContractAction;
use App\Enums\BillingPeriod;
use App\Enums\ClientType;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Http\Requests\Contracts\StoreContractRequest;
use App\Http\Requests\Contracts\UpdateContractRequest;
use App\Http\Resources\ContractAttachmentResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Product;
use App\Services\ContractAccrualService;
use App\Services\ContractCalculationService;
use App\Services\ContractFinanceService;
use App\Services\ContractLifecycleService;
use App\Services\ContractReceivablesService;
use App\Services\MovementBalanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ContractCalculationService $calculator, MovementBalanceService $balances, ContractReceivablesService $receivables): Response
    {
        Gate::authorize('viewAny', Contract::class);

        $company = $this->userCompany($request);
        app(ContractLifecycleService::class)->repairCompany($company->id);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $derivedIds = $this->derivedStatusIds($company, $status, $receivables);

        $contracts = Contract::query()
            ->with(['client', 'items.product', 'items.movementItems.movement', 'movements.items', 'freights'])
            ->withCount('attachments')
            ->whereBelongsTo($company)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('id', $search)
                        ->orWhere('worksite_address', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', function (Builder $query) use ($status, $derivedIds): void {
                match ($status) {
                    'PAYMENT_PENDING', 'READY_TO_FINALIZE' => $query->whereIn('id', $derivedIds),
                    default => $query->where('status', $status),
                };
            })
            ->latest('started_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Contract $contract): array => $this->contractData($contract, $calculator, $balances));

        return Inertia::render('contracts/Index', [
            'overview' => ['active' => $company->contracts()->where('status', 'ACTIVE')->count(), 'returned' => $company->contracts()->where('status', 'RETURNED')->count(), 'total' => $company->contracts()->count()],
            'contracts' => $contracts,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'contractStatuses' => $this->contractFilterStatuses(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Contract::class);

        $company = $this->userCompany($request);

        return Inertia::render('contracts/Create', [
            'clientTypes' => collect(ClientType::cases())->map(fn ($type) => ['value' => $type->value, 'label' => $type === ClientType::Individual ? 'Pessoa Física' : 'Pessoa Jurídica'])->all(),
            'clients' => $this->clientOptions($company),
            'products' => $this->productOptions($company),
            'billingPeriods' => $this->billingPeriods(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContractRequest $request, CreateContractAction $createContract): RedirectResponse|JsonResponse
    {
        $contract = $createContract->handle($this->userCompany($request), $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'contract' => ['id' => $contract->id],
            ], 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrato Criado com Sucesso.')]);

        return to_route('contracts.show', $contract);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Contract $contract, ContractCalculationService $calculator, MovementBalanceService $balances): Response
    {
        $contract = $this->findContractForUser($request, $contract);

        Gate::authorize('view', $contract);

        return Inertia::render('contracts/Show', [
            'contract' => $this->contractData($contract, $calculator, $balances),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Contract $contract, ContractCalculationService $calculator, MovementBalanceService $balances): Response
    {
        $contract = $this->findContractForUser($request, $contract);

        Gate::authorize('update', $contract);

        return Inertia::render('contracts/Edit', [
            'contract' => $this->contractData($contract, $calculator, $balances),
            'clients' => $this->clientOptions($contract->company),
            'products' => $this->productOptions($contract->company),
            'billingPeriods' => $this->billingPeriods(),
            'contractStatuses' => $this->contractStatuses(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContractRequest $request, Contract $contract, UpdateContractAction $updateContract): RedirectResponse
    {
        $contract = $this->findContractForUser($request, $contract);

        Gate::authorize('update', $contract);

        $contract = $updateContract->handle($contract, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrato Atualizado com Sucesso.')]);

        return to_route('contracts.show', $contract);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        abort(404);
    }

    private function findContractForUser(Request $request, Contract $contract): Contract
    {
        return Contract::query()
            ->with(['company', 'client', 'items.product', 'items.movementItems.movement', 'movements.items.contractItem.product', 'freights', 'attachments.uploader'])
            ->withCount('attachments')
            ->whereBelongsTo($this->userCompany($request))
            ->findOrFail($contract->id);
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
    private function clientOptions(Company $company): array
    {
        return Client::query()
            ->whereBelongsTo($company)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, type: string, type_label: string, default_price: string|null}>
     */
    private function productOptions(Company $company): array
    {
        return Product::query()
            ->whereBelongsTo($company)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'default_price'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type->value,
                'type_label' => $this->productTypeLabel($product->type),
                'default_price' => $product->default_price,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function billingPeriods(): array
    {
        return collect(BillingPeriod::cases())
            ->map(fn (BillingPeriod $period): array => [
                'value' => $period->value,
                'label' => $this->billingPeriodLabel($period),
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function contractStatuses(): array
    {
        return collect(ContractStatus::cases())
            ->map(fn (ContractStatus $status): array => [
                'value' => $status->value,
                'label' => $this->contractStatusLabel($status),
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function contractFilterStatuses(): array
    {
        return [
            ['value' => 'ACTIVE', 'label' => 'Ativos'],
            ['value' => 'PAYMENT_PENDING', 'label' => 'Pendentes de pagamento'],
            ['value' => 'READY_TO_FINALIZE', 'label' => 'Prontos para finalizar'],
            ['value' => 'FINALIZED', 'label' => 'Finalizados'],
            ['value' => 'CANCELLED', 'label' => 'Cancelados'],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function derivedStatusIds(Company $company, string $status, ContractReceivablesService $receivables): array
    {
        if (! in_array($status, ['PAYMENT_PENDING', 'READY_TO_FINALIZE'], true)) {
            return [];
        }

        return Contract::query()
            ->whereBelongsTo($company)
            ->where('status', ContractStatus::Returned->value)
            ->with(['client', 'payments', 'freights', 'items.product', 'items.movementItems.movement', 'movements.items'])
            ->get()
            ->map(fn (Contract $contract): array => $receivables->data($contract))
            ->filter(fn (array $row): bool => $status === 'PAYMENT_PENDING'
                ? $row['display_status'] === 'PAYMENT_PENDING'
                : $row['display_status'] === 'READY_TO_FINALIZE')
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function decimalToCents(string $amount): int
    {
        $normalized = str_contains($amount, '.')
            ? $amount
            : "{$amount}.00";

        [$reais, $cents] = explode('.', $normalized, 2);

        $cents = str_pad(substr($cents, 0, 2), 2, '0');

        return ((int) $reais * 100) + (int) $cents;
    }

    private function formatCents(int $cents): string
    {
        $reais = intdiv($cents, 100);

        $remainingCents = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return "{$reais}.{$remainingCents}";
    }

    private function contractData(Contract $contract, ContractCalculationService $calculator, MovementBalanceService $balances): array
    {
        $contract->loadMissing(['client', 'items.product', 'items.movementItems.movement', 'movements.items.contractItem.product', 'freights', 'attachments.uploader']);
        $repaired = app(ContractLifecycleService::class)->synchronize($contract);
        $contract->status = $repaired->status;
        $contract->ended_at = $repaired->ended_at;
        $quantities = $balances->currentQuantities($contract);
        $summary = app(ContractAccrualService::class)->summarize($contract);
        $calculation = $summary['calculation'];
        $freights = $contract->freights->sortByDesc('occurred_at')->values();
        $freightTotal = $summary['freight_total'];
        $totalAccrued = $summary['total_accrued'];
        $finance = app(ContractFinanceService::class)->summarize($contract, $summary);

        return [
            'id' => $contract->id,
            'number' => $contract->id,
            'status' => $contract->status->value,
            'status_label' => $this->contractStatusLabel($contract->status),
            'worksite_address' => $contract->worksite_address,
            'started_at' => $contract->started_at?->format('Y-m-d\TH:i'),
            'ended_at' => $contract->ended_at?->format('Y-m-d\TH:i'),
            'charge_saturdays' => $contract->charge_saturdays,
            'next_charge_date' => $contract->next_charge_date?->toDateString(),
            'charge_interval_days' => $contract->charge_interval_days,
            'notes' => $contract->notes,
            'attachments_count' => (int) ($contract->attachments_count ?? $contract->attachments()->count()),
            'attachments' => $contract->relationLoaded('attachments')
                ? ContractAttachmentResource::collection($contract->attachments->sortByDesc('created_at')->values())->resolve()
                : [],
            'calculated_until' => $calculation->calculatedUntil,
            'rental_total' => $calculation->rentalTotal,
            'calculation_complete' => $calculation->calculationComplete,
            'freight_count' => $freights->sum(fn ($freight): int => $freight->quantity),
            'freight_total' => $freightTotal,
            'total_accrued' => $totalAccrued,
            ...$finance,
            ...app(ContractLifecycleService::class)->presentation($contract, $finance['balance']),
            'client' => [
                'id' => $contract->client->id,
                'name' => $contract->client->name,
            ],
            'items' => $contract->items->map(fn ($item): array => [
                'id' => $item->id,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'type' => $item->product->type->value,
                    'type_label' => $this->productTypeLabel($item->product->type),
                    'default_price' => $item->product->default_price,
                ],
                'billing_period' => $item->billing_period->value,
                'billing_period_label' => $this->billingPeriodLabel($item->billing_period),
                'unit_price' => $item->unit_price,
                'current_quantity' => (int) ($quantities->get($item->id) ?? 0),
                'billable_quantity_days' => $calculation->item($item->id)?->billableQuantityDays ?? 0,
                'accrued_subtotal' => $calculation->item($item->id)?->subtotal,
            ])->all(),
            'movements' => $contract->movements
                ->sortByDesc('occurred_at')
                ->map(fn ($movement): array => [
                    'id' => $movement->id,
                    'type' => $movement->type->value,
                    'type_label' => $movement->type->value === 'WITHDRAWAL' ? 'Retirada' : 'DevoluÃ§Ã£o',
                    'occurred_at' => $movement->occurred_at?->format('Y-m-d\TH:i'),
                    'items' => $movement->items->map(fn ($movementItem): array => [
                        'id' => $movementItem->id,
                        'quantity' => $movementItem->quantity,
                        'product' => [
                            'id' => $movementItem->contractItem->product->id,
                            'name' => $movementItem->contractItem->product->name,
                        ],
                    ])->all(),
                ])->values()->all(),
            'freights' => $freights
                ->map(fn ($freight): array => [
                    'id' => $freight->id,
                    'quantity' => $freight->quantity,
                    'unit_amount' => $freight->unit_amount,
                    'total' => $this->formatCents($freight->quantity * $this->decimalToCents((string) $freight->unit_amount)),
                    'occurred_at' => $freight->occurred_at?->format('Y-m-d\TH:i'),
                    'notes' => $freight->notes,
                ])->all(),
        ];
    }

    private function contractStatusLabel(ContractStatus $status): string
    {
        return match ($status) {
            ContractStatus::Active => 'Ativo',
            ContractStatus::Returned => 'Devolvido',
            ContractStatus::Finalized => 'Finalizado',
            ContractStatus::Cancelled => 'Cancelado',
        };
    }

    private function billingPeriodLabel(BillingPeriod $period): string
    {
        return match ($period) {
            BillingPeriod::Day => 'Dia',
            BillingPeriod::Week => 'Semana',
            BillingPeriod::Month => 'MÃªs',
        };
    }

    private function productTypeLabel(ProductType $type): string
    {
        return match ($type) {
            ProductType::Quantity => 'Quantidade',
            ProductType::Individual => 'Individual',
        };
    }
}
