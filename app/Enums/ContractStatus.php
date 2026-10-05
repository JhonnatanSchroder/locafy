<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Active = 'ACTIVE';
    case Returned = 'RETURNED';
    case Finalized = 'FINALIZED';
    case Cancelled = 'CANCELLED';
}
