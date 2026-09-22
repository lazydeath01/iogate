<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Person;
use App\Models\User;
use App\Policies\Concerns\ManagesDepartmentHierarchy;

class PersonPolicy
{
    use ManagesDepartmentHierarchy;

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
    public function view(User $user, Person $person): bool
    {
        return $this->canManage($user, $person);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?Department $department = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->is_system) {
            return true;
        }

        if ($user->department_id === null) {
            return false;
        }

        return $department === null
            || in_array($department->id, $this->managedDepartmentIds($user->department_id), true);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Person $person): bool
    {
        return $this->canManage($user, $person);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Person $person): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Person $person): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Person $person): bool
    {
        return false;
    }

    private function canManage(User $user, Person $person): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->is_system) {
            return true;
        }

        if ($user->department_id === null) {
            return false;
        }

        return in_array($person->department_id, $this->managedDepartmentIds($user->department_id), true);
    }
}
