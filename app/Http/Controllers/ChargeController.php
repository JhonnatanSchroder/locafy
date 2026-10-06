<?php

namespace App\Http\Controllers;

use App\Actions\Charges\CancelChargeAction;
use App\Actions\Charges\CreateChargeAction;
use App\Actions\Charges\RegisterPaymentAction;
use App\Http\Requests\StoreChargeRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\ChargeResource;
use App\Http\Resources\ContractReceivableResource;
use App\Models\Charge;
use App\Models\Contract;
use App\Services\ContractReceivablesService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ChargeController extends Controller
{
    /** @return Builder<Charge> */
    private function query(Request $request): Builder
    {
        abort_unless($request->user()?->company_id !== null, 403);

        return Charge::query()->where('company_id', $request->user()->company_id)->with(['contract.client', 'payments']);
    }

    public function index(Request $request): Response|AnonymousResourceCollection
    {
        abort_unless($request->user()?->company_id !== null, 403);
        $filter = $request->string('filter')->toString();
        $search = $request->string('search')->trim()->toString();
        $rows = app(ContractReceivablesService::class)->rows($request->user()->company_id, $filter, $search);
        if ($request->filled('contract_id')) {
            $rows = $rows->where('contract_id', $request->integer('contract_id'))->values();
        }
        $page = max(1, $request->integer('page', 1));
        $charges = new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);
        if ($request->is('api/*')) {
            return ContractReceivableResource::collection($charges);
        }
        $outstanding = app(ContractReceivablesService::class)->rows($request->user()->company_id, 'outstanding');

        return Inertia::render('charges/Index', ['charges' => $charges, 'filter' => $filter, 'search' => $search, 'summary' => ['count' => $rows->count(), 'balance' => Money::format($outstanding->sum(fn ($row) => Money::cents($row['balance'])))]]);
    }

    public function show(Request $request, int $charge): ContractReceivableResource|Response
    {
        abort_unless($request->user()?->company_id !== null, 403);
        $contract = Contract::query()->where('company_id', $request->user()->company_id)->findOrFail($charge);
        $data = app(ContractReceivablesService::class)->data($contract, true);

        return $request->is('api/*') ? new ContractReceivableResource($data) : Inertia::render('charges/Show', ['charge' => $data]);
    }

    public function history(Request $request): Response|AnonymousResourceCollection
    {
        $charges = $this->query($request)->orderByDesc('due_date')->paginate(20)->withQueryString();
        if ($request->is('api/*')) {
            return ChargeResource::collection($charges);
        }

        return Inertia::render('charges/History', ['charges' => $charges->through(fn ($c) => (new ChargeResource($c))->resolve($request))]);
    }

    public function historicalShow(Request $request, int $charge): ChargeResource|Response
    {
        $resource = new ChargeResource($this->query($request)->findOrFail($charge));

        return $request->is('api/*') ? $resource : Inertia::render('charges/HistoryShow', ['charge' => $resource->resolve($request)]);
    }

    public function store(StoreChargeRequest $request, CreateChargeAction $action): JsonResponse|RedirectResponse
    {
        $contract = Contract::query()->where('company_id', $request->user()->company_id)->findOrFail($request->integer('contract_id'));
        $charge = $action->handle($contract, $request->validated());

        return $request->is('api/*') ? (new ChargeResource($charge))->response()->setStatusCode(201) : to_route('charges.history.show', $charge);
    }

    public function payment(StorePaymentRequest $request, int $charge, RegisterPaymentAction $action): ChargeResource|RedirectResponse
    {
        $charge = $this->query($request)->findOrFail($charge);
        $action->handle($charge, $request->validated());

        return $request->is('api/*') ? new ChargeResource($charge->fresh()) : back();
    }

    public function cancel(Request $request, int $charge, CancelChargeAction $action): RedirectResponse
    {
        $action->handle($this->query($request)->findOrFail($charge));

        return back();
    }
}
