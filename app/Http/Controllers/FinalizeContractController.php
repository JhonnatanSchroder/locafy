<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\FinalizeContractAction;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FinalizeContractController extends Controller
{
    public function __invoke(Request $request, Contract $contract, FinalizeContractAction $action): ContractResource|RedirectResponse
    {
        abort_unless($request->user()?->company_id !== null && $contract->company_id === $request->user()->company_id, 404);
        Gate::authorize('update', $contract);
        $contract = $action->handle($contract);
        if ($request->is('api/*')) {
            return new ContractResource($contract->load(['client', 'items.product']));
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contrato finalizado com sucesso.']);

        return back();
    }
}
