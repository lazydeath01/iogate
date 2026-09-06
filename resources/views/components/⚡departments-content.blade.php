<?php

use Livewire\Component;
use App\Models\Department;

new class extends Component {
    public $name = '';
    public $parent_code = '';
    public $is_active = true;
    private $parent_id = null;
    public $departments = null;
    public $map = [];
    public $selectedDepartmentId = null;
    public $editingDepartmentId = null;
    public $editingDepartmentName = '';

    public function mount() {
        $this->loadDepartments();
    }

    private function loadDepartments(): void
    {
        $this->departments = Department::all();
        // $this->departments = $this->buildTree($departments);
        $this->map = [];
        foreach ($this->departments as $department) {
            $parentId = $department->parent_id ?? 0;
            $this->map[$parentId][] = $department->id;
        }
    }

    public function addDepartment()
    {
        $this->validate([
            'name' => 'required|max:150',
            'parent_code' => 'max:50',
        ]);
        if (Department::where('code', $this->parent_code)->doesntExist() && $this->parent_code != '') {
            $this->addError('parent_code', 'Mã đơn vị cha không tồn tại.');
            return;
        } else {
            $this->parent_id = $this->parent_code != '' ? Department::where('code', $this->parent_code)->first()->id : null;
        }
        $department = Department::create([
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
        ]);
        $parent_id = $this->parent_id ? $this->parent_id : 0;

        $this->map[$parent_id][] = $department->id;
        $this->departments[] = $department;
        $this->reset(['name', 'is_active']);
    }

    public function selectParentDepartment(int $departmentId): void
    {
        if ($departmentId == $this->selectedDepartmentId) {
            $this->clearParentDepartment();
            return;
        }
        $department = $this->departments->firstWhere('id', $departmentId);

        if (! $department) {
            return;
        }

        $this->selectedDepartmentId = $department->id;
        $this->parent_code = $department->code;
    }

    public function updatedParentCode(?string $parentCode): void
    {
        $this->selectedDepartmentId = $parentCode === ''
            ? null
            : $this->departments->firstWhere('code', $parentCode)?->id;
    }

    public function clearParentDepartment(): void
    {
        $this->selectedDepartmentId = null;
        $this->parent_code = '';
    }

    public function startRenamingDepartment(int $departmentId): void
    {
        $department = $this->departments->firstWhere('id', $departmentId);

        if (! $department) {
            return;
        }

        $this->editingDepartmentId = $department->id;
        $this->editingDepartmentName = $department->name;
    }

    public function saveDepartmentName(): void
    {
        $this->validate([
            'editingDepartmentName' => 'required|max:150',
        ]);

        $department = $this->departments->firstWhere('id', $this->editingDepartmentId);

        if (! $department) {
            return;
        }

        $department->update(['name' => $this->editingDepartmentName]);
        $this->editingDepartmentId = null;
        $this->editingDepartmentName = '';
    }

    public function cancelRenamingDepartment(): void
    {
        $this->editingDepartmentId = null;
        $this->editingDepartmentName = '';
    }

    public function setDepartmentStatus(int $departmentId, bool $isActive, bool $includeChildren = false): void
    {
        $department = $this->departments->firstWhere('id', $departmentId);

        if (! $department) {
            return;
        }

        $departmentIds = [$department->id];

        if (! $isActive || $includeChildren) {
            $departmentIds = array_merge($departmentIds, $this->descendantDepartmentIds($department->id));
        }

        Department::whereIn('id', $departmentIds)->update(['is_active' => $isActive]);

        $this->departments->whereIn('id', $departmentIds)->each(function (Department $department) use ($isActive): void {
            $department->is_active = $isActive;
        });
    }

    private function descendantDepartmentIds(int $departmentId): array
    {
        $descendantIds = [];
        $pendingIds = $this->map[$departmentId] ?? [];

        while ($pendingIds !== []) {
            $childId = array_pop($pendingIds);
            $descendantIds[] = $childId;
            $pendingIds = array_merge($pendingIds, $this->map[$childId] ?? []);
        }

        return $descendantIds;
    }
};
?>

<div class="h-full bg-slate-100 p-4 sm:p-6">
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="grid min-h-128 grid-cols-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:grid-cols-[minmax(20rem,0.85fr)_minmax(0,1.15fr)]">
            <section class="bg-slate-50 max-h-full">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Thêm mới đơn vị</h2>
                    <p class="mt-1 text-sm text-slate-500">Tạo một đơn vị trong hệ thống.</p>
                </div>
                <form wire:submit.prevent="addDepartment" class="flex flex-col gap-5 p-5">
                    <label class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                        <span>Tên đơn vị</span>
                        <input class="rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"
                            type="text" placeholder="Nhập tên đơn vị" wire:model="name">
                        @error('name')
                            @if ($message=="The name field is required.")
                            <span class="font-normal text-red-600">Tên đơn vị không được bỏ trống.</span>
                            @else
                            <span class="font-normal text-red-600">{{ $message }}</span>
                            @endif
                        @enderror
                    </label>
                    <label class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                        <span>Mã đơn vị cha</span>
                        <div class="flex items-center gap-2">
                            <select class="block min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"
                                wire:model.live="parent_code">
                                <option value="">Không có đơn vị cha</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->code }}">
                                        {{ $department->code }} - {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="clearParentDepartment"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white font-semibold text-slate-500 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600"
                                aria-label="Xóa đơn vị cha" title="Xóa đơn vị cha">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        @error('parent_code')
                            <span class="font-normal text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                    <button class="mt-1 inline-flex w-full items-center justify-center rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2"
                        type="submit">Thêm đơn vị</button>
                </form>
            </section>
            <section class="min-w-0 border-b border-slate-200 lg:border-b-0 lg:border-l">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Danh sách đơn vị</h2>
                    <p class="mt-1 text-sm text-slate-500">Chọn một đơn vị để đặt làm đơn vị cha.</p>
                </div>
                @if ($departments->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Danh sách trống</p>
                @endif
                <div class="flex max-h-[calc(100vh-14rem)] min-h-0 flex-col gap-1 overflow-y-auto p-4" wire:key="department-list">
                    @foreach ($map[0] ?? [] as $departmentId)
                        @include('components.department-tree-node', [
                            'departmentId' => $departmentId,
                            'departmentsById' => $departments->keyBy('id'),
                            'map' => $map,
                            'depth' => 0,
                        ])
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</div>
