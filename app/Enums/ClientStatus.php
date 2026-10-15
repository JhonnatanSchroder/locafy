<?php

namespace App\Enums;

enum ClientStatus: string
{
    case Active = 'ACTIVE';
    case Blocked = 'BLOCKED';
    case Inactive = 'INACTIVE';
}
