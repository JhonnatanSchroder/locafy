<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipmentResource;
use App\Models\Company;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return EquipmentResource::collection(
            Equipment::query()
                ->with('product')
                ->whereBelongsTo($this->userCompany($request))
                ->orderBy('name')
                ->paginate(15)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $equipment): EquipmentResource
    {
        return new EquipmentResource(
            Equipment::query()
                ->with('product')
                ->whereBelongsTo($this->userCompany($request))
                ->findOrFail($equipment)
        );
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;

        abort_if($company === null, 403);

        return $company;
    }
}
