<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case Available = 'AVAILABLE';
    case Rented = 'RENTED';
    case Maintenance = 'MAINTENANCE';
    case Inactive = 'INACTIVE';
}
