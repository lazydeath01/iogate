<?php

use Livewire\Component;
use App\Models\PersonType;

new class extends Component {
    public $personTypes = [];
    public $nameInput = '';
    public $descriptionInput = '';
    public $editingPersonTypeId = null;
    public $editingPersonTypeName = '';
    public $editingPersonTypeDescription = '';
    public $editingPersonTypeField = 'name';
    public function mount()
    {
        $this->personTypes = PersonType::all();
    }

    public function createPersonType()
    {
        $this->validate([
            'nameInput' => 'required|max:100',
            'descriptionInput' => 'max:255',
        ]);
        $personType = PersonType::create([
            'name' => $this->nameInput,
            'description' => $this->descriptionInput,
        ]);
        $this->personTypes[] = $personType;
        $this->reset(['nameInput', 'descriptionInput']);
    }

    public function startRenamingPersonType(int $personTypeId): void
    {
        $personType = $this->personTypes->firstWhere('id', $personTypeId);

        if (!$personType) {
            return;
        }

        $this->editingPersonTypeId = $personType->id;
        $this->editingPersonTypeName = $personType->name;
        $this->editingPersonTypeDescription = $personType->description ?? '';
        $this->editingPersonTypeField = 'name';
    }

    public function savePersonTypeName(): void
    {
        $this->validate([
            'editingPersonTypeName' => 'required|max:100',
            'editingPersonTypeDescription' => 'max:255',
        ]);

        $personType = $this->personTypes->firstWhere('id', $this->editingPersonTypeId);

        if (!$personType) {
            return;
        }

        $personType->update([
            'name' => $this->editingPersonTypeName,
            'description' => $this->editingPersonTypeDescription,
        ]);
        $this->editingPersonTypeId = null;
        $this->editingPersonTypeName = '';
        $this->editingPersonTypeDescription = '';
        $this->editingPersonTypeField = 'name';
    }

    public function editPersonTypeName(): void
    {
        $this->resetValidation();
        $this->editingPersonTypeField = 'name';
    }

    public function editPersonTypeDescription(): void
    {
        $this->resetValidation();
        $this->editingPersonTypeField = 'description';
    }

    public function cancelRenamingPersonType(): void
    {
        $this->editingPersonTypeId = null;
        $this->editingPersonTypeName = '';
        $this->editingPersonTypeDescription = '';
        $this->editingPersonTypeField = 'name';
    }
};
?>

<div class="min-h-full bg-slate-100 p-4 sm:p-6">
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Vai trò</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Tạo nhóm người để áp dụng quyền ra vào chi tiết cho từng
                    nhóm.</p>
            </div>
        </div>
        <div
            class="grid overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:grid-cols-[minmax(19rem,0.8fr)_minmax(0,1.2fr)]">
            <section class="border-b border-slate-200 bg-slate-50 lg:border-b-0 lg:border-r">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Tạo vai trò mới</h2>
                    <p class="mt-1 text-sm text-slate-500">Đặt tên rõ ràng để dễ phân quyền sau này.</p>
                </div>
                <div class="flex flex-col gap-5 p-5">
                    <form wire:submit.prevent="createPersonType" class="flex flex-col gap-5">
                        <label for="nameInput" class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                            <span>Tên vai trò</span>
                            <input wire:model="nameInput" id="nameInput" type="text" placeholder="Ví dụ: Cán bộ, Lao động hợp đồng..."
                                class="rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                            @error('nameInput')
                                <span class="font-normal text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                        <label for="desInput" class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                            <span>Ghi chú <span class="font-normal text-slate-400">(không bắt buộc)</span></span>
                            <textarea id="desInput" wire:model="descriptionInput" rows="3" placeholder="Mô tả ngắn về nhóm người này"
                                class="resize-none rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"></textarea>
                            @error('descriptionInput')
                                <span class="font-normal text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                        <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
                            Tạo vai trò
                        </button>
                    </form>
                </div>
            </section>

            <section class="min-w-0">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div class="flex flex-row justify-between items-center w-full">
                        <div>
                            <h2 class="font-semibold text-slate-900">Danh sách vai trò</h2>
                            <p class="mt-1 text-sm text-slate-500">Các nhóm người đã được tạo trong hệ thống.</p>
                        </div>
                        <div
                            class="hidden rounded-md border border-slate-200 bg-white px-2 py-2 text-right shadow-sm sm:block">
                            <p class=" font-bold text-slate-900">{{ $personTypes->count() }} <span
                                    class="text-xs font-medium text-slate-500">đơn vị</span></p>
                        </div>
                    </div>
                    <span
                        class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 sm:hidden">{{ $personTypes->count() }}</span>
                </div>
                @if ($personTypes->isEmpty())
                    <div class="flex min-h-64 flex-col items-center justify-center px-5 py-10 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-400"
                            aria-hidden="true">+</div>
                        <h3 class="mt-4 font-semibold text-slate-900">Chưa có vai trò nào</h3>
                        <p class="mt-1 max-w-xs text-sm text-slate-500">Tạo vai trò đầu tiên bằng biểu mẫu bên cạnh để
                            bắt đầu phân quyền.</p>
                    </div>
                @else
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        @foreach ($personTypes as $item)
                            @if ($editingPersonTypeId === $item->id)
                                <div class="fixed inset-0 z-40 bg-slate-950/60" aria-hidden="true"></div>
                            @endif
                            <article wire:key="person-type-{{ $item->id }}" x-data="{ menuOpen: false }"
                                class="{{ $editingPersonTypeId === $item->id ? 'relative z-50' : '' }} rounded-md border border-slate-200 bg-white p-4 transition hover:border-teal-300 hover:shadow-sm">
                                <div class="flex items-start gap-3">
                                    <div class="min-w-0 flex-1">
                                        @if ($editingPersonTypeId === $item->id)
                                            <form wire:key="editing-person-type-{{ $item->id }}-{{ $editingPersonTypeField }}"
                                                wire:submit="savePersonTypeName"
                                                x-on:keydown="if ($event.key === 'Tab') { const focusable = [...$el.querySelectorAll('input, button, [tabindex]:not([tabindex=\'-1\'])')].filter((element) => !element.disabled && element.offsetParent !== null); const first = focusable[0]; const last = focusable[focusable.length - 1]; if ($event.shiftKey && document.activeElement === first) { $event.preventDefault(); last.focus(); } else if (!$event.shiftKey && document.activeElement === last) { $event.preventDefault(); first.focus(); } }"
                                                x-init="$nextTick(() => { $refs.editingInput.focus(); $refs.editingInput.select(); })"
                                                class="flex items-start gap-2">
                                                <div class="min-w-0 flex-1">
                                                    @if ($editingPersonTypeField === 'name')
                                                        <label for="editingPersonTypeName-{{ $item->id }}" class="sr-only">Tên vai trò</label>
                                                        <input wire:model="editingPersonTypeName" id="editingPersonTypeName-{{ $item->id }}" type="text"
                                                            x-ref="editingInput"
                                                            class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm text-slate-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                                                        @error('editingPersonTypeName')
                                                            <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span>
                                                        @enderror
                                                        <p wire:click="editPersonTypeDescription" role="button" tabindex="0"
                                                            wire:keydown.enter="editPersonTypeDescription"
                                                            class="mt-1 cursor-pointer text-sm leading-5 text-slate-500 hover:text-teal-700">
                                                            {{ $editingPersonTypeDescription ?: 'Chưa có ghi chú' }}
                                                        </p>
                                                    @else
                                                        <h3 wire:click="editPersonTypeName" role="button" tabindex="0"
                                                            wire:keydown.enter="editPersonTypeName"
                                                            class="cursor-pointer font-semibold text-slate-900 hover:text-teal-700">
                                                            {{ $editingPersonTypeName }}
                                                        </h3>
                                                        <label for="editingPersonTypeDescription-{{ $item->id }}" class="sr-only">Ghi chú</label>
                                                        <input wire:model="editingPersonTypeDescription" id="editingPersonTypeDescription-{{ $item->id }}" type="text"
                                                            x-ref="editingInput" placeholder="Mô tả ngắn về nhóm người này"
                                                            class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm text-slate-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                                                        @error('editingPersonTypeDescription')
                                                            <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span>
                                                        @enderror
                                                    @endif
                                                </div>
                                                <div class="flex shrink-0 flex-col gap-1">
                                                    <button type="submit" class="rounded-md px-2 py-1 text-sm font-semibold text-teal-700 hover:bg-teal-50">Lưu</button>
                                                    <button type="button" wire:click="cancelRenamingPersonType" class="rounded-md px-2 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-100">Hủy</button>
                                                </div>
                                            </form>
                                        @else
                                            <h3 class="font-semibold text-slate-900">{{ $item->name }}</h3>
                                            @if ($item->description)
                                                <p class="mt-1 text-sm leading-5 text-slate-500">{{ $item->description }}</p>
                                            @else
                                                <p class="mt-1 text-sm italic text-slate-400">Chưa có ghi chú</p>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="relative shrink-0">
                                        <button type="button" x-on:click="menuOpen = !menuOpen" :aria-expanded="menuOpen.toString()"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded text-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                            aria-label="Tùy chọn vai trò" title="Tùy chọn vai trò">
                                            &hellip;
                                        </button>
                                        <div x-show="menuOpen" x-cloak x-on:click.outside="menuOpen = false"
                                            class="absolute right-0 top-full z-10 mt-1 w-28 rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg">
                                            <button type="button" wire:click="startRenamingPersonType({{ $item->id }})"
                                                x-on:click="menuOpen = false"
                                                class="w-full px-3 py-2 text-left text-slate-700 hover:bg-slate-50">Chỉnh sửa</button>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
