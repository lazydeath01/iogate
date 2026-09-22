<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Policies\Concerns\ManagesDepartmentHierarchy;

class UserPolicy
{
    use ManagesDepartmentHierarchy;

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, User $model): bool
    {
        return $this->canManage($user, $model);
    }

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

    public function update(User $user, User $model): bool
    {
        return $this->canManage($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    private function canManage(User $user, User $managedUser): bool
    {
        if (! $user->is_active || $managedUser->is_system) {
            return false;
        }

        if ($user->is_system) {
            return true;
        }

        if ($user->department_id === null || $managedUser->department_id === null) {
            return false;
        }

        return in_array($managedUser->department_id, $this->managedDepartmentIds($user->department_id), true);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
