<?php

use App\Models\Department;
use App\Models\Person;
use App\Models\PersonType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedDepartmentId = null;
    public $departments;
    public $treeDepartments;
    public array $departmentRows = [];
    public array $expandedDepartmentIds = [];
    public $persons;
    public $personTypes;
    public string $fullName = '';
    public string $identityNumber = '';
    public string $phone = '';
    public ?int $personTypeId = null;
    public ?int $editingPersonId = null;
    public array $createLogs = [];
    public ?string $notificationType = null;
    public string $notificationMessage = '';
    public bool $closeDialogAfterNotification = false;

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

    public function savePerson(): void
    {
        try {
            $validated = $this->validate([
                'fullName' => ['required', 'string', 'max:150'],
                'identityNumber' => ['nullable', 'string', 'max:12'],
                'phone' => ['required', 'string', 'max:20'],
                'personTypeId' => ['required', 'integer', 'exists:person_types,id'],
            ]);

            $department = Department::findOrFail($this->selectedDepartmentId);
            Gate::authorize('create', [Person::class, $department]);

            Person::create([
                'full_name' => $validated['fullName'],
                'identity_number' => $validated['identityNumber'] ?: null,
                'phone' => $validated['phone'],
                'person_type_id' => $validated['personTypeId'],
                'department_id' => $department->id,
                'is_active' => true,
            ]);

            $this->createLogs[] = [
                'created_at' => now()->format('H:i:s'),
                'message' => "Đã tạo nhân sự {$validated['fullName']}.",
            ];
            $this->reset(['fullName', 'identityNumber', 'phone', 'personTypeId']);
            $this->loadPersons();
            $this->showNotification('success', 'Tạo nhân sự thành công.');
        } catch (ValidationException $exception) {
            $this->showNotification('error', 'Không thể tạo nhân sự. Vui lòng kiểm tra lại thông tin.');
            throw $exception;
        } catch (\Throwable) {
            $this->showNotification('error', 'Không thể tạo nhân sự. Vui lòng thử lại.');
        }
    }

    public function startEditingPerson(int $personId): void
    {
        $person = Person::findOrFail($personId);

        if (! Gate::allows('update', $person)) {
            $this->closeDialogAfterNotification = true;
            $this->showNotification('error', 'Bạn không có quyền chỉnh sửa nhân sự này.');

            return;
        }

        $this->resetValidation();
        $this->closeDialogAfterNotification = false;
        $this->editingPersonId = $person->id;
        $this->fullName = $person->full_name;
        $this->identityNumber = $person->identity_number ?? '';
        $this->phone = $person->phone;
        $this->personTypeId = $person->person_type_id;
    }

    public function updatePerson(): void
    {
        $person = Person::findOrFail($this->editingPersonId);

        if (! Gate::allows('update', $person)) {
            $this->closeDialogAfterNotification = true;
            $this->showNotification('error', 'Bạn không có quyền chỉnh sửa nhân sự này.');

            return;
        }

        try {
            $validated = $this->validate([
                'fullName' => ['required', 'string', 'max:150'],
                'identityNumber' => ['nullable', 'string', 'max:12'],
                'phone' => ['required', 'string', 'max:20'],
                'personTypeId' => ['required', 'integer', 'exists:person_types,id'],
            ]);

            $identityNumber = $validated['identityNumber'] ?: null;

            if (
                $person->full_name === $validated['fullName']
                && $person->identity_number === $identityNumber
                && $person->phone === $validated['phone']
                && $person->person_type_id === (int) $validated['personTypeId']
            ) {
                $this->closeDialogAfterNotification = false;
                $this->showNotification('warning', 'Thông tin không thay đổi. Chưa có nội dung mới để lưu.');

                return;
            }

            $person->update([
                'full_name' => $validated['fullName'],
                'identity_number' => $identityNumber,
                'phone' => $validated['phone'],
                'person_type_id' => $validated['personTypeId'],
            ]);

            $this->closeDialogAfterNotification = true;
            $this->loadPersons();
            $this->showNotification('success', 'Cập nhật nhân sự thành công.');
        } catch (ValidationException $exception) {
            $this->showNotification('error', 'Không thể cập nhật nhân sự. Vui lòng kiểm tra lại thông tin.');
            throw $exception;
        } catch (\Throwable) {
            $this->showNotification('error', 'Không thể cập nhật nhân sự. Vui lòng thử lại.');
        }
    }

    public function togglePersonStatus(int $personId): void
    {
        $person = Person::findOrFail($personId);

        if (! Gate::allows('update', $person)) {
            $this->closeDialogAfterNotification = true;
            $this->showNotification('error', 'Bạn không có quyền thay đổi trạng thái nhân sự này.');

            return;
        }

        $person->update(['is_active' => ! $person->is_active]);
        $this->closeDialogAfterNotification = true;
        $this->loadPersons();
        $this->showNotification('success', $person->is_active ? 'Đã bật nhân sự.' : 'Đã tắt nhân sự.');
    }

    public function closePersonDialog(): void
    {
        $this->resetPersonForm();
    }

    private function resetPersonForm(): void
    {
        $this->reset(['editingPersonId', 'fullName', 'identityNumber', 'phone', 'personTypeId']);
        $this->resetValidation();
    }

    public function closeNotification(): void
    {
        if ($this->closeDialogAfterNotification) {
            $this->resetPersonForm();
        }

        $this->closeDialogAfterNotification = false;
        $this->notificationType = null;
        $this->notificationMessage = '';
    }

    private function showNotification(string $type, string $message): void
    {
        $this->notificationType = $type;
        $this->notificationMessage = $message;
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
        $this->personTypes = PersonType::query()->orderBy('name')->get();
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

<div class="h-full bg-slate-100 p-4 sm:p-6" x-data="{
    dialog: null,
    openDialog(name) {
        this.dialog = name;
        this.$nextTick(() => this.focusFirstElement());
    },
    init() {
        this.$watch('dialog', (value) => value && this.$nextTick(() => this.focusFirstElement()));
    },
    focusableElements() {
        const activePanel = this.$wire.notificationType !== null ? this.$refs.notificationOverlay : this.$refs.dialogPanel;

        return [...activePanel.querySelectorAll(`button, input, select, textarea, [href], [tabindex]:not([tabindex=&quot;-1&quot;])`)]
            .filter((element) => !element.disabled && element.offsetParent !== null);
    },
    focusFirstElement() {
        this.focusableElements()[0]?.focus();
    },
    trapFocus(event) {
        if (this.dialog === null) {
            return;
        }

        const focusable = this.focusableElements();

        if (focusable.length === 0) {
            event.preventDefault();
            (this.$wire.notificationType !== null ? this.$refs.notificationOverlay : this.$refs.dialogPanel).focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        const activePanel = this.$wire.notificationType !== null ? this.$refs.notificationOverlay : this.$refs.dialogPanel;

        if (!activePanel.contains(document.activeElement)) {
            event.preventDefault();
            first.focus();
            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
}" x-on:keydown.escape.window="dialog = null">
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
                <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-900">Nhân sự</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $departments->firstWhere('id', $selectedDepartmentId)?->name }} · {{ $persons->count() }} người</p>
                    </div>
                    <button type="button" x-on:click="openDialog('create')" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-800">Tạo mới</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-3xl table-fixed divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr><th class="px-5 py-3">Họ và tên</th><th class="w-40 px-5 py-3">Loại</th><th class="w-36 px-5 py-3">Điện thoại</th><th class="w-36 px-5 py-3">Trạng thái</th><th class="w-40 px-5 py-3 text-right">Thao tác</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($persons as $person)
                                <tr wire:key="person-{{ $person->id }}" class="text-slate-700">
                                    <td class="wrap-break-word whitespace-normal px-5 py-3">{{ $person->full_name }}</td>
                                    <td class="wrap-break-word whitespace-normal px-5 py-3">{{ $person->personType?->name ?? '—' }}</td>
                                    <td class="wrap-break-word whitespace-normal px-5 py-3">{{ $person->phone }}</td>
                                    <td class="wrap-break-word whitespace-normal px-5 py-3"><span class="font-medium {{ $person->is_active ? 'text-teal-700' : 'text-slate-500' }}">{{ $person->is_active ? 'Đang hoạt động' : 'Đã tắt' }}</span></td>
                                    <td class="px-5 py-3"><div class="flex items-center justify-end gap-2"><button type="button" x-on:click="openDialog('edit')" wire:click="startEditingPerson({{ $person->id }})" aria-label="Sửa thông tin {{ $person->full_name }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-600 hover:bg-teal-50 hover:text-teal-800">Sửa</button><button type="button" x-on:click="openDialog('notice')" wire:click="togglePersonStatus({{ $person->id }})" aria-label="{{ $person->is_active ? 'Tắt' : 'Bật' }} nhân sự {{ $person->full_name }}" class="rounded-md border px-3 py-1.5 text-xs font-semibold {{ $person->is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-teal-200 text-teal-700 hover:bg-teal-50' }}">{{ $person->is_active ? 'Tắt' : 'Bật' }}</button></div></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Chưa có nhân sự trong đơn vị này.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div x-cloak x-show="dialog !== null" x-transition.opacity class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/65 p-4" role="dialog" aria-modal="true">
            <div x-ref="dialogPanel" tabindex="-1" x-on:keydown.tab.window="trapFocus($event)" class="relative min-h-[min(22rem,90vh)] max-h-[90vh] w-full max-w-lg overflow-y-auto border-2 border-slate-300 bg-white shadow-xl">
                <div class="flex items-start justify-between border-b-2 border-slate-200 bg-slate-50 px-5 py-4">
                    <div>
                        <h2 class="font-semibold text-slate-900" x-text="dialog === 'edit' ? 'Chỉnh sửa nhân sự' : 'Tạo nhân sự'">Tạo nhân sự</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $departments->firstWhere('id', $selectedDepartmentId)?->name }}</p>
                    </div>
                    <button type="button" x-on:click="dialog = null" wire:click="closePersonDialog" class="text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="Đóng">&times;</button>
                </div>

                <form x-show="dialog === 'create'" wire:submit="savePerson" class="flex flex-col gap-4 p-5">
                    <label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Họ và tên<input wire:model="fullName" type="text" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('fullName') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Số định danh<input wire:model="identityNumber" type="text" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('identityNumber') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Số điện thoại<input wire:model="phone" type="text" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('phone') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Loại nhân sự<select wire:model="personTypeId" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0"><option value="">Chọn loại nhân sự</option>@foreach ($personTypes as $personType)<option value="{{ $personType->id }}">{{ $personType->name }}</option>@endforeach</select>@error('personTypeId') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
                    <button type="submit" class="rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white hover:bg-teal-800" wire:loading.attr="disabled">Tạo nhân sự</button>
                    <section class="border-t border-slate-200 pt-4" aria-labelledby="person-create-log-title">
                        <div class="flex items-center justify-between gap-3"><h3 id="person-create-log-title" class="font-semibold text-slate-900">Nhật ký tạo nhân sự</h3><span class="text-xs text-slate-500">{{ count($createLogs) }} hoạt động</span></div>
                        <div class="mt-2 flex h-24 flex-col gap-1 overflow-y-auto pr-2 text-sm text-slate-600" role="log" aria-live="polite">
                            @if ($createLogs === [])
                                <p class="text-slate-500">Chưa có hoạt động trong phiên này.</p>
                            @else
                                @foreach ($createLogs as $log)
                                    <div wire:key="person-create-log-{{ $loop->index }}" class="flex gap-2 leading-5 {{ $loop->last ? 'font-semibold text-teal-800' : '' }}"><time class="shrink-0 text-xs text-slate-400">{{ $log['created_at'] }}</time><span>{{ $log['message'] }}</span></div>
                                @endforeach
                            @endif
                        </div>
                    </section>
                </form>

                <form x-show="dialog === 'edit'" wire:submit="updatePerson" class="flex flex-col gap-4 p-5">
                    <label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Họ và tên<input wire:model="fullName" type="text" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('fullName') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Số định danh<input wire:model="identityNumber" type="text" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('identityNumber') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Số điện thoại<input wire:model="phone" type="text" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('phone') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Loại nhân sự<select wire:model="personTypeId" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0"><option value="">Chọn loại nhân sự</option>@foreach ($personTypes as $personType)<option value="{{ $personType->id }}">{{ $personType->name }}</option>@endforeach</select>@error('personTypeId') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
                    <button type="submit" class="rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white hover:bg-teal-800" wire:loading.attr="disabled">Lưu thay đổi</button>
                </form>

                <div x-show="$wire.notificationType !== null" x-cloak x-ref="notificationOverlay" x-effect="if ($wire.notificationType !== null) { $nextTick(() => $refs.confirmButton?.focus()); } else if (dialog !== null) { $nextTick(() => focusFirstElement()); }" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 p-4 text-center sm:gap-3 sm:p-6" :class="$wire.notificationType === 'success' ? 'bg-teal-50' : ($wire.notificationType === 'warning' ? 'bg-amber-50' : 'bg-red-50')" role="alert" aria-live="assertive">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full" :class="$wire.notificationType === 'success' ? 'bg-teal-100 text-teal-700' : ($wire.notificationType === 'warning' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700')">
                        <span x-show="$wire.notificationType === 'success'" class="text-3xl">&#10003;</span>
                        <span x-show="$wire.notificationType !== 'success'" class="text-3xl">!</span>
                    </span>
                    <h3 class="text-lg font-semibold" :class="$wire.notificationType === 'success' ? 'text-teal-900' : ($wire.notificationType === 'warning' ? 'text-amber-900' : 'text-red-900')" x-text="$wire.notificationType === 'success' ? 'Thành công' : ($wire.notificationType === 'warning' ? 'Không có thay đổi' : 'Không thể thực hiện')"></h3>
                    <p class="text-sm" :class="$wire.notificationType === 'success' ? 'text-teal-800' : ($wire.notificationType === 'warning' ? 'text-amber-800' : 'text-red-800')">{{ $notificationMessage }}</p>
                    <button x-ref="confirmButton" type="button" x-on:click="if ($wire.closeDialogAfterNotification) dialog = null" wire:click="closeNotification" class="mt-2 rounded-md bg-slate-800 px-6 py-2.5 font-semibold text-white hover:bg-slate-900">Xác nhận</button>
                </div>
            </div>
        </div>
    </div>
</div>