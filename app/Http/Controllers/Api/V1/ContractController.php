<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use App\Models\Company;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ContractResource::collection(
            Contract::query()
                ->with(['client', 'items.product', 'items.movementItems.movement', 'movements.items'])
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
                ->with(['client', 'items.product', 'items.movementItems.movement', 'movements.items'])
                ->whereBelongsTo($this->userCompany($request))
                ->findOrFail($contract)
        );
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;

        abort_if($company === null, 403);

        return $company;
    }
}
