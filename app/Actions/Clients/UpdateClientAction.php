<?php

namespace App\Actions\Clients;

use App\Models\Client;

class UpdateClientAction
{
    /**
     * @param  array{type: string, name: string, document?: string|null, phone?: string|null, residential_address?: string|null, notes?: string|null}  $data
     */
    public function handle(Client $client, array $data): Client
    {
        $client->update($data);

        return $client->refresh();
    }
}
