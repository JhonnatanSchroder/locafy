<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'ADMIN';
    case Attendant = 'ATENDENTE';
    case Financial = 'FINANCEIRO';
    case Delivery = 'ENTREGADOR';
}
