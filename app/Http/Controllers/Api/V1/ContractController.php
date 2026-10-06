<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contracts\CreateContractAction;
use App\Actions\Contracts\UpdateContractAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\StoreContractRequest;
use App\Http\Requests\Contracts\UpdateContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Company;
use App\Models\Contract;
use App\Services\ContractLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        app(ContractLifecycleService::class)->repairCompany($this->userCompany($request)->id);

        return ContractResource::collection(
            Contract::query()
                ->with(['client', 'items.product', 'items.movementItems.movement', 'movements.items', 'freights:id,contract_id,quantity,unit_amount'])
                ->whereBelongsTo($this->userCompany($request))
                ->latest('started_at')
                ->latest('id')
                ->paginate(15)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $contract): ContractResource
    {
        return new ContractResource(
            Contract::query()
                ->with(['client', 'items.product', 'items.movementItems.movement', 'movements.items', 'freights'])
                ->whereBelongsTo($this->userCompany($request))
                ->findOrFail($contract)
        );
    }

    public function store(StoreContractRequest $request, CreateContractAction $action): JsonResponse
    {
        return (new ContractResource($action->handle($this->userCompany($request), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateContractRequest $request, Contract $contract, UpdateContractAction $action): ContractResource
    {
        abort_unless($contract->company_id === $request->user()->company_id, 404);

        return new ContractResource($action->handle($contract, $request->validated()));
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;

        abort_if($company === null, 403);

        return $company;
    }
}
