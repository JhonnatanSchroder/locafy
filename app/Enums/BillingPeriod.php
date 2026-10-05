<?php

namespace App\Enums;

enum BillingPeriod: string
{
    case Day = 'DAY';
    case Week = 'WEEK';
    case Month = 'MONTH';
}
