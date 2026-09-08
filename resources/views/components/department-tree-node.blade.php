@php
    $department = $departmentsById[$departmentId];
    $children = $map[$departmentId] ?? [];
@endphp

<div x-data="{
    open: true,
    menuOpen: false,
    confirmOpen: false,
    menuTop: 0,
    menuLeft: 0,
    toggleMenu() {
        const button = this.$refs.menuButton;
        const menuHeight = 82;
        const menuWidth = 144;
        const gap = 4;
        const rect = button.getBoundingClientRect();
        const opensDown = window.innerHeight - rect.bottom >= menuHeight + gap;

        this.menuTop = opensDown ? rect.bottom + gap : rect.top - menuHeight - gap;
        this.menuLeft = Math.max(8, rect.right - menuWidth);
        this.menuOpen = !this.menuOpen;
    }
}" wire:key="department-{{ $department->id }}">
    <div class="flex items-center gap-1 rounded-md px-1 py-1 transition duratino-25  {{ $selectedDepartmentId === $department->id ? 'bg-teal-100 ring-1 ring-teal-600/70' : '' }}"
        style="padding-left: {{ $depth * 1.25 }}rem">
        @if (count($children) > 0)
            <button type="button" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded text-sm text-slate-500 hover:bg-slate-200 hover:text-slate-800" x-on:click="open = !open"
                x-text="open ? '-' : '+'" :aria-expanded="open.toString()"
                aria-label="Mở hoặc đóng đơn vị con"></button>
        @else
            <span class="w-7 shrink-0"></span>
        @endif
        @if ($editingDepartmentId === $department->id)
            <form wire:submit.prevent="saveDepartmentName" class="flex min-w-0 flex-1 items-center gap-2">
                <input type="text" wire:model="editingDepartmentName"
                    class="min-w-0 flex-1 rounded border border-teal-400 bg-white px-2 py-1 text-sm text-slate-900 outline-none focus:ring-2 focus:ring-teal-600/20"
                    aria-label="Tên đơn vị">
                <button type="submit" class="text-xs font-semibold text-teal-700 hover:text-teal-900">Lưu</button>
                <button type="button" wire:click="cancelRenamingDepartment"
                    class="text-xs font-semibold text-slate-500 hover:text-slate-800">Hủy</button>
            </form>
        @else
            <button type="button" wire:click="selectParentDepartment({{ $department->id }})"
                class="flex min-w-0 flex-1 items-center gap-2 rounded px-2 py-1 text-left {{ $selectedDepartmentId === $department->id ? 'text-teal-950' : 'text-slate-700' }}"
            aria-pressed="{{ $selectedDepartmentId === $department->id ? 'true' : 'false' }}">
                <span class="shrink-0 rounded border px-2 py-0.5 text-xs font-semibold {{ $selectedDepartmentId === $department->id ? 'border-teal-500 bg-teal-200 text-teal-950' : 'border-slate-200 bg-slate-100 text-slate-600' }}">
                    {{ $department->code }}
                </span>
                <span class="truncate {{ $department->is_active ? '' : 'text-slate-400 line-through' }}">{{ $department->name }}</span>
            </button>
        @endif
        <div class="relative shrink-0">
            <button type="button" x-ref="menuButton" x-on:click="toggleMenu()" :aria-expanded="menuOpen.toString()"
                class="inline-flex h-8 w-8 items-center justify-center rounded text-lg text-slate-400 hover:bg-slate-200 hover:text-slate-700"
                aria-label="Chỉnh sửa đơn vị" title="Chỉnh sửa đơn vị">
                &hellip;
            </button>
            <div x-show="menuOpen" x-cloak x-on:click.outside="menuOpen = false"
                x-bind:style="`top: ${menuTop}px; left: ${menuLeft}px`"
                class="fixed z-50 flex w-36 flex-col rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg">
                <button type="button" wire:click="startRenamingDepartment({{ $department->id }})"
                    x-on:click="menuOpen = false"
                    class="px-3 py-2 text-left text-slate-700 hover:bg-slate-50">Đổi tên</button>
                <button type="button" x-on:click="menuOpen = false; confirmOpen = true"
                    class="px-3 py-2 text-left text-slate-700 hover:bg-slate-50">
                    {{ $department->is_active ? 'Vô hiệu hóa' : 'Kích hoạt' }}
                </button>
            </div>
        </div>
    </div>

    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-60 flex items-center justify-center bg-slate-900/40 p-4"
        x-on:keydown.escape.window="confirmOpen = false">
        <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl" x-on:click.stop>
            @if ($department->is_active)
                <h3 class="font-semibold text-slate-900">Vô hiệu hóa đơn vị?</h3>
                <p class="mt-2 text-sm text-slate-600">
                    @if (count($children) > 0)
                        Đơn vị này và toàn bộ đơn vị con sẽ bị vô hiệu hóa.
                    @else
                        Đơn vị này sẽ bị vô hiệu hóa.
                    @endif
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" x-on:click="confirmOpen = false"
                        class="rounded-md px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Hủy</button>
                    <button type="button" wire:click="setDepartmentStatus({{ $department->id }}, false)"
                        x-on:click="confirmOpen = false"
                        class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Vô hiệu hóa
                    </button>
                </div>
            @else
                <h3 class="font-semibold text-slate-900">Kích hoạt đơn vị?</h3>
                <p class="mt-2 text-sm text-slate-600">Bạn muốn kích hoạt cả các đơn vị con không?</p>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" x-on:click="confirmOpen = false"
                        class="rounded-md px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Hủy</button>
                    <button type="button" wire:click="setDepartmentStatus({{ $department->id }}, true, false)"
                        x-on:click="confirmOpen = false"
                        class="rounded-md border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50">
                        Chỉ đơn vị này
                    </button>
                    <button type="button" wire:click="setDepartmentStatus({{ $department->id }}, true, true)"
                        x-on:click="confirmOpen = false"
                        class="rounded-md bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800">
                        Cả đơn vị con
                    </button>
                </div>
            @endif
        </div>
    </div>

    @if (count($children) > 0)
        <div x-show="open">
            @foreach ($children as $childId)
                @include('components.department-tree-node', [
                    'departmentId' => $childId,
                    'departmentsById' => $departmentsById,
                    'map' => $map,
                    'depth' => $depth + 1,
                ])
            @endforeach
        </div>
    @endif
</div>
