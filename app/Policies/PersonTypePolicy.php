<?php

namespace App\Policies;

use App\Models\PersonType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PersonTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PersonType $personType): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_system;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PersonType $personType): bool
    {
        return $user->is_system;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PersonType $personType): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PersonType $personType): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PersonType $personType): bool
    {
        return false;
    }
}
