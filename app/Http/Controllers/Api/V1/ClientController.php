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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();
        $sqlite = (new Client)->getConnection()->getDriverName() === 'sqlite';
        if ($sqlite && $search !== '') {
            (new Client)->getConnection()->getPdo()->sqliteCreateFunction(
                'locafy_client_search',
                static fn ($value): string => Str::lower(Str::ascii((string) $value)),
                1
            );
        }

        return ClientResource::collection(
            Client::query()
                ->whereBelongsTo($this->userCompany($request))
                ->when($search !== '', function (Builder $query) use ($search, $sqlite): void {
                    $query->where(function (Builder $query) use ($search, $sqlite): void {
                        if ($sqlite) {
                            $query->whereRaw('locafy_client_search(name) LIKE ?', ['%'.Str::lower(Str::ascii($search)).'%']);
                        } else {
                            $query->where('name', 'like', "%{$search}%");
                        }
                        $query->orWhere('phone', 'like', "%{$search}%")->orWhere('document', 'like', "%{$search}%");
                        $digits = preg_replace('/\D/', '', $search);
                        if ($digits !== '' && preg_match('/^[\d\s()+.\-\/]+$/', $search)) {
                            foreach (['phone', 'document'] as $column) {
                                $expression = $column;
                                foreach ([' ', '-', '(', ')', '+', '.', '/'] as $separator) {
                                    $expression = "REPLACE({$expression}, '{$separator}', '')";
                                }
                                $query->orWhereRaw("{$expression} LIKE ?", ["%{$digits}%"]);
                            }
                        }
                    });
                })
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString()
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
