<?php

use Livewire\Component;
use App\Models\Department;

new class extends Component {
    public $name = '';
    public $code = '';
    public $parent_code = '';
    public $is_active = true;
    private $parent_id = null;
    public $departments = null;
    public $map = [];

    public function mount() {
        $this->departments = Department::select([
            'id',
            'parent_id',
            'name',
            'code',
            'is_active'
        ])->get();
        // $this->departments = $this->buildTree($departments);

        foreach ($this->departments as $department) {
            $parentId = $department->parent_id ?? 0;
            if ($parentId == 0) {
                $this->map[]=$department->id;
            } else {
                $this->map[$parentId][]= $department->id;
            }
        }

        // dd($this->map);
    }

    private function buildTree($departments, $parentId = null) {
        $tree = [];

        foreach ($departments as $department) {
            if ($department->parent_id == $parentId) {
                $department->children = $this->buildTree(
                    $departments,
                    $department->id
                );
                $tree[] = $department;
            }
        }

        return $tree;
    }

    public function addDepartment()
    {
        $this->validate([
            'name' => 'required|max:150',
            'code' => 'max:50',
            'parent_code' => 'max:50',
        ]);
        if (Department::where('code', $this->code)->exists()) {
            $this->addError('code', 'Mã đơn vị đã tồn tại.');
            return;
        }
        if (Department::where('code', $this->parent_code)->doesntExist() && $this->parent_code != '') {
            $this->addError('parent_code', 'Mã đơn vị cha không tồn tại.');
            return;
        } else {
            $this->parent_id = $this->parent_code != '' ? Department::where('code', $this->parent_code)->first()->id : null;
        }
        Department::create([
            'name' => $this->name,
            'code' => $this->code,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
        ]);
        $this->reset(['name', 'code', 'parent_code', 'is_active']);
    }
    public function updateDepartment($data) {}
};
?>

<div x-data="{ openCreateDialog: false }">
    <div x-show="openCreateDialog" id="create-dialog"
        class="flex absolute top-0 left-0 w-screen h-screen bg-black/50 z-10 items-center justify-center">
        <div class=" relative w-100 bg-amber-50">
            <button class=" absolute top-0 right-0 w-10 h-10 bg-red-800" x-on:click="openCreateDialog = false"> X
            </button>
            <h1 class="text-2xl font-semibold text-center">
                Thêm đơn vị
            </h1>
            <form wire:submit.prevent="addDepartment" class="flex flex-col gap-2 p-4">
                <label class="block">
                    Tên đơn vị:
                    <input class="block border border-black" type="text" placeholder="Tên đơn vị" wire:model="name">
                    @error('name')
                        <span class="text-red-600">{{ $message }}</span>
                    @enderror
                </label>
                <label class="block">
                    Mã đơn vị:
                    <input class="block border border-black" type="text" placeholder="Mã đơn vị" wire:model="code">
                    @error('code')
                        <span class="text-red-600">{{ $message }}</span>
                    @enderror
                </label>
                <label class="block">
                    Mã đơn vị cha:
                    <input class="block border border-black" type="text" placeholder="Mã đơn vị cha"
                        wire:model="parent_code">
                    @error('parent_code')
                        <span class="text-red-600">{{ $message }}</span>
                    @enderror
                </label>
                <button class="border border-black px-2 py-1 hover:bg-gray-700 hover:text-white font-semibold w-60"
                    type="submit">Thêm đơn vị</button>
            </form>
        </div>
    </div>
    <div class=" flex flex-col items-center w-full h-full">
        <div class="flex flex-row justify-start items-center w-full">
            <button type="button" x-on:click="openCreateDialog = true"
                class="border border-black px-2 py-1 hover:bg-gray-700 hover:text-white font-semibold">
                Tạo mới
            </button>
        </div>
        <div class="flex flex-row w-full h-full">
            <div class="flex flex-col gap-2 w-1/2 h-full bg-amber-50">
                department_list
                @foreach ($departments as $department)
                    <div class="flex flex-row items-center">
                        <div class=" text-sm border border-blue-500 text-blue-600 px-2 bg-blue-50">
                            {{ $department['code'] }}
                        </div>
                        <div class="pl-1">
                            {{ $department['name'] }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="w-full h-full bg-amber-950">
                department_person_list
            </div>
        </div>
    </div>
</div>
