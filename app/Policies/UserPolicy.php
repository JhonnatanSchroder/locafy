<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAdministrateCompany();
    }

    public function create(User $user): bool
    {
        return $user->canAdministrateCompany();
    }

    public function update(User $user, User $managedUser): bool
    {
        return $user->canAdministrateCompany()
            && $user->company_id === $managedUser->company_id;
    }

    public function deactivate(User $user, User $managedUser): bool
    {
        return $this->update($user, $managedUser);
    }

    public function resetPassword(User $user, User $managedUser): bool
    {
        return $this->update($user, $managedUser);
    }
}
