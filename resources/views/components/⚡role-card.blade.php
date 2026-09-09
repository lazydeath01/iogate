<?php

use Livewire\Component;
use App\Models\PersonType;

new class extends Component {
    public PersonType $personType;
    public $editing = false;
    public $editingPersonTypeName = '';
    public $editingPersonTypeDescription = '';
    public $editingPersonTypeField = 'name';

    public function startEditing(): void
    {
        $this->editing = true;
        $this->editingPersonTypeName = $this->personType->name;
        $this->editingPersonTypeDescription = $this->personType->description ?? '';
        $this->editingPersonTypeField = 'name';
    }

    public function save(): void
    {
        $this->validate([
            'editingPersonTypeName' => 'required|max:100',
            'editingPersonTypeDescription' => 'max:255',
        ]);

        $this->personType->update([
            'name' => $this->editingPersonTypeName,
            'description' => $this->editingPersonTypeDescription,
        ]);
        $this->personType->refresh();
        $this->editing = false;
        $this->resetEditingState();
    }

    public function editName(): void
    {
        $this->resetValidation();
        $this->editingPersonTypeField = 'name';
    }

    public function editDescription(): void
    {
        $this->resetValidation();
        $this->editingPersonTypeField = 'description';
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->resetEditingState();
    }

    private function resetEditingState(): void
    {
        $this->editingPersonTypeName = '';
        $this->editingPersonTypeDescription = '';
        $this->editingPersonTypeField = 'name';
    }
};
?>

<div x-data="{ menuOpen: false }" class="contents">
    @if ($editing)
        <div class="fixed inset-0 z-40 bg-slate-950/60" aria-hidden="true"></div>
    @endif
    <article
        class="{{ $editing ? 'relative z-50' : '' }} rounded-md border border-slate-200 bg-white p-4 transition hover:border-teal-300 hover:shadow-sm">
    <div class="flex items-start gap-3">
        <div class="min-w-0 flex-1">
            @if ($editing)
                <form wire:submit="save"
                    wire:key="editing-person-type-{{ $personType->id }}-{{ $editingPersonTypeField }}"
                    x-on:keydown="if ($event.key === 'Tab') { const focusable = [...$el.querySelectorAll('input, button, [tabindex]:not([tabindex=\'-1\'])')].filter((element) => !element.disabled && element.offsetParent !== null); const first = focusable[0]; const last = focusable[focusable.length - 1]; if ($event.shiftKey && document.activeElement === first) { $event.preventDefault(); last.focus(); } else if (!$event.shiftKey && document.activeElement === last) { $event.preventDefault(); first.focus(); } }"
                    x-init="$nextTick(() => { $refs.editingInput.focus(); $refs.editingInput.select(); })"
                    class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
                        @if ($editingPersonTypeField === 'name')
                            <label for="editingPersonTypeName-{{ $personType->id }}" class="sr-only">Tên vai trò</label>
                            <input wire:model="editingPersonTypeName" id="editingPersonTypeName-{{ $personType->id }}" type="text"
                                x-ref="editingInput"
                                class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm text-slate-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                            @error('editingPersonTypeName')
                                <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span>
                            @enderror
                            <p wire:click="editDescription" wire:focus="editDescription" role="button" tabindex="0"
                                wire:keydown.enter="editDescription"
                                class="mt-1 cursor-pointer text-sm leading-5 text-slate-500 hover:text-teal-700">
                                {{ $editingPersonTypeDescription ?: 'Chưa có ghi chú' }}
                            </p>
                        @else
                            <h3 wire:click="editName" wire:focus="editName" role="button" tabindex="0" wire:keydown.enter="editName"
                                class="cursor-pointer font-semibold text-slate-900 hover:text-teal-700">
                                {{ $editingPersonTypeName }}
                            </h3>
                            <label for="editingPersonTypeDescription-{{ $personType->id }}" class="sr-only">Ghi chú</label>
                            <input wire:model="editingPersonTypeDescription" id="editingPersonTypeDescription-{{ $personType->id }}" type="text"
                                x-ref="editingInput" placeholder="Mô tả ngắn về nhóm người này"
                                class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm text-slate-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                            @error('editingPersonTypeDescription')
                                <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span>
                            @enderror
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-col gap-1">
                        <button type="submit" class="rounded-md px-2 py-1 text-sm font-semibold text-teal-700 hover:bg-teal-50">Lưu</button>
                        <button type="button" wire:click="cancel" class="rounded-md px-2 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-100">Hủy</button>
                    </div>
                </form>
            @else
                <h3 class="font-semibold text-slate-900">{{ $personType->name }}</h3>
                @if ($personType->description)
                    <p class="mt-1 text-sm leading-5 text-slate-500">{{ $personType->description }}</p>
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
                <button type="button" wire:click="startEditing" x-on:click="menuOpen = false"
                    class="w-full px-3 py-2 text-left text-slate-700 hover:bg-slate-50">Chỉnh sửa</button>
            </div>
        </div>
    </div>
    </article>
</div>