<?php

use App\Models\Department;
use App\Models\Person;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedDepartmentId = null;
    public $departments;
    public $treeDepartments;
    public array $departmentRows = [];
    public array $expandedDepartmentIds = [];
    public $persons;

    public function mount(): void
    {
        Gate::authorize('viewAny', Person::class);

        $this->loadPersons();
        $this->selectDepartment($this->departments->first()?->id);
    }

    public function selectDepartment(?int $departmentId): void
    {
        if ($departmentId === null || ! $this->departments->contains('id', $departmentId)) {
            return;
        }

        $this->selectedDepartmentId = $departmentId;
        $this->loadPersons();
    }

    public function toggleDepartment(int $departmentId): void
    {
        if (in_array($departmentId, $this->expandedDepartmentIds, true)) {
            $this->expandedDepartmentIds = array_values(array_diff($this->expandedDepartmentIds, [$departmentId]));
        } else {
            $this->expandedDepartmentIds[] = $departmentId;
        }

        $this->departmentRows = $this->buildDepartmentRows();
    }

    private function loadPersons(): void
    {
        $actor = auth()->user();
        $allDepartments = Department::query()->orderBy('code')->get();

        if ($actor->is_system) {
            $this->departments = $allDepartments;
            $this->treeDepartments = $allDepartments;
        } else {
            $departmentById = $allDepartments->keyBy('id');
            $managedDepartmentIds = [];
            $pendingDepartmentIds = $actor->department_id === null ? [] : [$actor->department_id];

            while ($pendingDepartmentIds !== []) {
                $departmentId = array_pop($pendingDepartmentIds);

                if (in_array($departmentId, $managedDepartmentIds, true)) {
                    continue;
                }

                $managedDepartmentIds[] = $departmentId;

                foreach ($allDepartments->where('parent_id', $departmentId) as $childDepartment) {
                    $pendingDepartmentIds[] = $childDepartment->id;
                }
            }

            $this->departments = $allDepartments
                ->whereIn('id', $managedDepartmentIds)
                ->filter(fn (Department $department) => Gate::forUser($actor)->allows('view', $department))
                ->values();

            $treeDepartmentIds = $managedDepartmentIds;
            $departmentId = $actor->department_id;

            while ($departmentId !== null) {
                $treeDepartmentIds[] = $departmentId;
                $departmentId = $departmentById->get($departmentId)?->parent_id;
            }

            $this->treeDepartments = $allDepartments->whereIn('id', array_unique($treeDepartmentIds))->values();
        }

        if ($this->expandedDepartmentIds === []) {
            $this->expandedDepartmentIds = $this->treeDepartments
                ->filter(fn (Department $department) => $this->treeDepartments->contains('parent_id', $department->id))
                ->pluck('id')
                ->all();
        }

        $this->departmentRows = $this->buildDepartmentRows();
        $this->persons = Person::query()
            ->with(['department', 'personType'])
            ->orderBy('full_name')
            ->get()
            ->filter(fn (Person $person) => Gate::forUser($actor)->allows('view', $person)
                && ($this->selectedDepartmentId === null || $person->department_id === $this->selectedDepartmentId))
            ->values();
    }

    /**
     * @return array<int, array{department: Department, depth: int}>
     */
    private function buildDepartmentRows(?int $parentId = null, int $depth = 0): array
    {
        $rows = [];

        foreach ($this->treeDepartments->where('parent_id', $parentId) as $department) {
            $rows[] = ['department' => $department, 'depth' => $depth];

            if (in_array($department->id, $this->expandedDepartmentIds, true)) {
                $rows = array_merge($rows, $this->buildDepartmentRows($department->id, $depth + 1));
            }
        }

        return $rows;
    }
};
?>

<div class="h-full bg-slate-100 p-4 sm:p-6">
    <div class="mx-auto flex max-w-7xl flex-col gap-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Nhân sự</h1>
            <p class="mt-1 text-sm text-slate-500">Chọn đơn vị để xem danh sách nhân sự.</p>
        </div>

        <div class="grid gap-0 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <section class="border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Đơn vị</h2>
                    <p class="mt-1 text-sm text-slate-500">Chọn đơn vị để xem nhân sự.</p>
                </div>
                <div class="flex max-h-[calc(100vh-15rem)] flex-col gap-1 overflow-y-auto p-3">
                    @foreach ($departmentRows as $row)
                        @php($department = $row['department'])
                        @php($hasChildren = $treeDepartments->contains('parent_id', $department->id))
                        @php($isSelectable = $departments->contains('id', $department->id))
                        <div wire:key="person-department-tree-{{ $department->id }}" class="flex items-stretch gap-1" style="padding-left: {{ $row['depth'] * 1.1 }}rem">
                            @if ($hasChildren)
                                <button type="button" wire:click="toggleDepartment({{ $department->id }})" class="w-7 shrink-0 rounded-md text-sm text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="{{ in_array($department->id, $expandedDepartmentIds, true) ? 'Thu gọn' : 'Mở rộng' }}">
                                    {{ in_array($department->id, $expandedDepartmentIds, true) ? '▾' : '▸' }}
                                </button>
                            @else
                                <span class="w-7 shrink-0"></span>
                            @endif
                            <button type="button" @if ($isSelectable) wire:click="selectDepartment({{ $department->id }})" @else disabled title="Bạn không có quyền quản lý đơn vị này" @endif class="min-w-0 flex-1 rounded-md px-2 py-2 text-left text-sm transition {{ $selectedDepartmentId === $department->id ? 'bg-teal-50 font-semibold text-teal-800 ring-1 ring-teal-200' : ($isSelectable ? 'text-slate-700 hover:bg-slate-50' : 'cursor-not-allowed text-slate-400') }}">
                                <span class="line-clamp-3 block wrap-break-word">{{ $department->name }}</span><span class="block text-xs font-normal text-slate-400">{{ $department->code }}</span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="min-w-0 border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Nhân sự</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $departments->firstWhere('id', $selectedDepartmentId)?->name }} · {{ $persons->count() }} người</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-3xl table-fixed divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr><th class="w-36 px-5 py-3">Mã</th><th class="px-5 py-3">Họ và tên</th><th class="w-40 px-5 py-3">Loại</th><th class="w-36 px-5 py-3">Điện thoại</th><th class="w-36 px-5 py-3">Trạng thái</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($persons as $person)
                                <tr wire:key="person-{{ $person->id }}" class="text-slate-700">
                                    <td class="wrap-break-word whitespace-normal px-5 py-3 font-medium text-slate-900">{{ $person->code }}</td>
                                    <td class="wrap-break-word whitespace-normal px-5 py-3">{{ $person->full_name }}</td>
                                    <td class="wrap-break-word whitespace-normal px-5 py-3">{{ $person->personType?->name ?? '—' }}</td>
                                    <td class="wrap-break-word whitespace-normal px-5 py-3">{{ $person->phone }}</td>
                                    <td class="wrap-break-word whitespace-normal px-5 py-3"><span class="font-medium {{ $person->is_active ? 'text-teal-700' : 'text-slate-500' }}">{{ $person->is_active ? 'Đang hoạt động' : 'Đã tắt' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Chưa có nhân sự trong đơn vị này.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>