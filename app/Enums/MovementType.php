<?php

namespace App\Enums;

enum MovementType: string
{
    case Withdrawal = 'WITHDRAWAL';
    case Return = 'RETURN';
}
