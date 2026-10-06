<?php

namespace App\Actions\Charges;

/** @deprecated Receivables are now computed from contracts, without materializing cycles. */
class EnsureDueChargesExist
{
    public function handle(?int $companyId = null): int
    {
        return 0;
    }
}
