<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RegisterContractPaymentAction;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\ContractReceivableResource;
use App\Models\Contract;
use App\Services\ContractReceivablesService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ContractPaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Contract $contract, RegisterContractPaymentAction $action, ContractReceivablesService $receivables): ContractReceivableResource|RedirectResponse
    {
        abort_unless($contract->company_id === $request->user()->company_id, 404);
        $action->handle($contract, $request->validated());

        if (! $request->is('api/*')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Pagamento registrado com sucesso.']);
        }

        return $request->is('api/*') ? new ContractReceivableResource($receivables->data($contract->fresh(), true)) : back();
    }
}
