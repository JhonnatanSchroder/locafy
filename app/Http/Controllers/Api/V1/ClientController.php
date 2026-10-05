<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Clients\CreateClientAction;
use App\Actions\Clients\UpdateClientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
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
            $this->findClientForUser($request, $client)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientRequest $request, CreateClientAction $createClient): JsonResponse
    {
        $client = $createClient->handle(
            $this->userCompany($request),
            $request->validated()
        );

        return (new ClientResource($client))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, int $client, UpdateClientAction $updateClient): ClientResource
    {
        return new ClientResource(
            $updateClient->handle(
                $this->findClientForUser($request, $client),
                $request->validated()
            )
        );
    }

    private function findClientForUser(Request $request, int $client): Client
    {
        return Client::query()
            ->whereBelongsTo($this->userCompany($request))
            ->findOrFail($client);
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;

        abort_if($company === null, 403);

        return $company;
    }
}
