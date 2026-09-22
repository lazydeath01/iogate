<?php

namespace App\Policies\Concerns;

use App\Models\Department;

trait ManagesDepartmentHierarchy
{
    /**
     * @return array<int>
     */
    private function managedDepartmentIds(int $departmentId): array
    {
        $childrenByParent = Department::query()
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id')
            ->map(fn ($children) => $children->pluck('id')->all())
            ->all();
        $managedDepartmentIds = [$departmentId];
        $pendingDepartmentIds = [$departmentId];

        while ($pendingDepartmentIds !== []) {
            $currentDepartmentId = array_pop($pendingDepartmentIds);

            foreach ($childrenByParent[$currentDepartmentId] ?? [] as $childDepartmentId) {
                $managedDepartmentIds[] = $childDepartmentId;
                $pendingDepartmentIds[] = $childDepartmentId;
            }
        }

        return $managedDepartmentIds;
    }
}
