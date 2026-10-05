<?php

namespace App\Http\Controllers;

use App\Actions\Freights\CreateFreightAction;
use App\Http\Requests\StoreFreightRequest;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FreightController extends Controller
{
    public function store(
        StoreFreightRequest $request,
        Contract $contract,
        CreateFreightAction $action
    ): RedirectResponse {
        abort_unless(
            $contract->company_id === $request->user()->company_id,
            404
        );

        $action->handle(
            $contract,
            $request->validated()
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Frete registrado com sucesso.')]);

        return back();
    }
}
