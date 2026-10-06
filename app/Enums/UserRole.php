<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'ADMIN';
    case Operator = 'OPERATOR';
    case Attendant = 'ATENDENTE';
    case Financial = 'FINANCEIRO';
    case Delivery = 'ENTREGADOR';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Operator => 'Operador',
            self::Attendant => 'Atendente',
            self::Financial => 'Financeiro',
            self::Delivery => 'Entregador',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
