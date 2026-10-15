<?php

namespace App\Actions\Clients;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Company;

class CreateClientAction
{
    /**
     * @param  array{type: string, name: string, document?: string|null, phone?: string|null, residential_address?: string|null, notes?: string|null}  $data
     */
    public function handle(Company $company, array $data): Client
    {
        return $company->clients()->create(['status' => ClientStatus::Active, ...$data]);
    }
}
