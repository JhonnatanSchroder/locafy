<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Freights\CreateFreightAction;
use App\Actions\Freights\UpdateFreightAction;
use App\Actions\Movements\CreateMovementAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Movements\StoreMovementRequest;
use App\Http\Requests\StoreFreightRequest;
use App\Http\Requests\UpdateFreightRequest;
use App\Models\Contract;
use App\Models\Freight;
use Illuminate\Http\JsonResponse;

class ContractOperationController extends Controller
{
    public function movement(StoreMovementRequest $request, Contract $contract, CreateMovementAction $action): JsonResponse
    {
        abort_unless($contract->company_id === $request->user()->company_id, 404);

        return response()->json(['data' => $action->handle($contract, $request->validated())], 201);
    }

    public function freight(StoreFreightRequest $request, Contract $contract, CreateFreightAction $action): JsonResponse
    {
        abort_unless($contract->company_id === $request->user()->company_id, 404);

        return response()->json(['data' => $action->handle($contract, $request->validated())], 201);
    }

    public function updateFreight(UpdateFreightRequest $request, Contract $contract, Freight $freight, UpdateFreightAction $action): JsonResponse
    {
        abort_unless($contract->company_id === $request->user()->company_id && $freight->contract_id === $contract->id && $freight->company_id === $contract->company_id, 404);

        return response()->json(['data' => $action->handle($freight, $request->validated())]);
    }
}
