<?php

namespace App\Http\Controllers;

use App\Actions\Freights\CreateFreightAction;
use App\Http\Requests\StoreFreightRequest;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use App\Actions\Freights\UpdateFreightAction;
use App\Http\Requests\UpdateFreightRequest;
use App\Models\Freight;

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

    public function update(
        UpdateFreightRequest $request,
        Contract $contract,
        Freight $freight,
        UpdateFreightAction $action
    ): RedirectResponse {
        abort_unless(
            $contract->company_id === $request->user()->company_id,
            404
        );

        abort_unless(
            $freight->contract_id === $contract->id
            && $freight->company_id === $contract->company_id,
            404
        );

        $action->handle(
            $freight,
            $request->validated()
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Frete atualizado com sucesso.'),
        ]);

        return back();
    }
}
