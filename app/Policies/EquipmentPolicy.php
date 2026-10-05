<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

class EquipmentPolicy
{
    /**
     * Determine whether the user can view any equipments.
     */
    public function viewAny(User $user): bool
    {
        return $user->company_id !== null;
    }

    /**
     * Determine whether the user can view the equipment.
     */
    public function view(User $user, Equipment $equipment): bool
    {
        return $this->belongsToUsersCompany($user, $equipment);
    }

    /**
     * Determine whether the user can create equipments.
     */
    public function create(User $user): bool
    {
        return $user->company_id !== null;
    }

    /**
     * Determine whether the user can update the equipment.
     */
    public function update(User $user, Equipment $equipment): bool
    {
        return $this->belongsToUsersCompany($user, $equipment);
    }

    private function belongsToUsersCompany(User $user, Equipment $equipment): bool
    {
        return $user->company_id !== null
            && $user->company_id === $equipment->company_id;
    }
}
