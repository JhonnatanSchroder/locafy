<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ClientResource::collection(
            Client::query()
                ->whereBelongsTo($this->userCompany($request))
                ->orderBy('name')
                ->paginate(15)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $client): ClientResource
    {
        return new ClientResource(
            Client::query()
                ->whereBelongsTo($this->userCompany($request))
                ->findOrFail($client)
        );
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;

        abort_if($company === null, 403);

        return $company;
    }
}
