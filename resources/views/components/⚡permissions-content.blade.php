<?php

use App\Models\Department;
use App\Models\Person;
use App\Models\PersonType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Livewire\Component;
use Carbon\Carbon;

new class extends Component
{
    public string $targetType = 'department';
    public ?int $targetId = null;
    public string $targetSearch = '';
    public string $permissionMode = 'deny';
    public bool $showErrorDialog = false;
    public bool $showSuccessDialog = false;
    public array $highlightedErrorAreas = [];
    public string $constraintsJson = '{}';
    public array $allowedDates = [];
    public array $excludedDates = [];
    public array $allowedTimes = [];
    public array $excludedTimes = [];
    public array $allowedWeekdays = [];
    public ?int $maxEntriesPerDay = null;
    public ?int $maxEntries = null;
    public ?int $maxDurationPerSession = null;
    public ?int $maxDuration = null;
    public $departments;
    public $personTypes;
    public $persons;

    public function mount(): void
    {
        $this->authorizeSystemUser();
        $this->loadTargets();
    }

    public function updatedTargetType(): void
    {
        $this->targetId = null;
        $this->targetSearch = '';
        $this->clearErrorState();
        $this->resetPermissionForm();
    }

    public function selectTarget(int $targetId): void
    {
        $this->targetId = $targetId;
        $this->clearErrorState();
        $this->loadPermission($targetId);
    }

    public function updatedTargetId(?int $targetId): void
    {
        $this->clearErrorState();
        $this->loadPermission($targetId);
    }

    public function closeErrorDialog(): void
    {
        foreach (array_keys($this->getErrorBag()->toArray()) as $field) {
            $this->highlightedErrorAreas[] = match ($field) {
                'targetType', 'targetId' => 'target',
                'permissionMode' => 'mode',
                'allowedDates', 'excludedDates', 'allowedTimes', 'excludedTimes', 'allowedWeekdays', 'limits' => $field,
                'constraintsJson' => 'constraints',
                default => 'mode',
            };
        }

        $this->highlightedErrorAreas = array_values(array_unique($this->highlightedErrorAreas));
        $this->showErrorDialog = false;
    }

    public function closeSuccessDialog(): void
    {
        $this->showSuccessDialog = false;
    }

    public function updatedPermissionMode(): void
    {
        $this->clearErrorState();
    }

    public function savePermission(): void
    {
        $this->authorizeSystemUser();
        $this->showErrorDialog = true;
        if ($this->permissionMode === 'constrained' && $this->hasStructuredConstraintInput()) {
            $this->constraintsJson = $this->constraintsFromForm();
        }

        $this->validate([
            'targetType' => 'required|in:department,role,person',
            'targetId' => 'required|integer|min:1',
            'permissionMode' => 'required|in:deny,unrestricted,constrained',
            'constraintsJson' => 'required_if:permissionMode,constrained|json',
        ], [
            'targetId.required' => 'Hãy chọn đối tượng cần cấp quyền.',
            'constraintsJson.json' => 'Điều kiện phải là JSON hợp lệ.',
        ]);

        $target = $this->target();
        $permission = match ($this->permissionMode) {
            'deny' => null,
            'unrestricted' => [],
            default => $this->validatedConstraints(),
        };

        $target->forceFill(['permission' => $permission])->save();
        $this->resetValidation();
        $this->showErrorDialog = false;
        $this->showSuccessDialog = true;
        $this->clearErrorHighlights();
        $this->loadTargets();
        $this->loadPermission($target->id);
        session()->flash('permissionSaved', 'Đã cập nhật quyền ra vào.');
    }

    private function loadTargets(): void
    {
        $this->departments = Department::query()->orderBy('code')->get(['id', 'code', 'name', 'permission']);
        $this->personTypes = PersonType::query()->orderBy('name')->get(['id', 'name', 'permission']);
        $this->persons = Person::query()->orderBy('full_name')->get(['id', 'code', 'full_name', 'permission']);
    }

    public function hasErrorHighlight(string $area): bool
    {
        return in_array($area, $this->highlightedErrorAreas, true);
    }

    private function clearErrorHighlights(): void
    {
        $this->highlightedErrorAreas = [];
    }

    private function clearErrorState(): void
    {
        $this->resetValidation();
        $this->showErrorDialog = false;
        $this->clearErrorHighlights();
    }

    public function getVisibleTargetsProperty(): Collection
    {
        $search = mb_strtolower(trim($this->targetSearch));
        $targets = match ($this->targetType) {
            'department' => $this->departments,
            'role' => $this->personTypes,
            default => $this->persons,
        };

        if ($search === '') {
            return $targets;
        }

        return $targets->filter(function (Department|PersonType|Person $target) use ($search): bool {
            $searchable = match ($this->targetType) {
                'department' => $target->code.' '.$target->name,
                'role' => $target->name,
                default => $target->code.' '.$target->full_name,
            };

            return str_contains(mb_strtolower($searchable), $search);
        });
    }

    private function loadPermission(?int $targetId): void
    {
        if ($targetId === null) {
            $this->resetPermissionForm();
            return;
        }

        $target = $this->target();
        $permission = $target->permission;

        if ($permission === null) {
            $this->resetPermissionForm();
        } elseif ($permission === []) {
            $this->permissionMode = 'unrestricted';
            $this->resetConstraintForm();
        } else {
            $this->permissionMode = 'constrained';
            $this->constraintsJson = json_encode($permission, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->fillConstraintForm($permission);
        }
    }

    public function addRange(string $type): void
    {
        $property = $this->rangeProperty($type);
        $this->{$property}[] = ['start' => '', 'end' => ''];
    }

    public function removeRange(string $type, int $index): void
    {
        $property = $this->rangeProperty($type);
        unset($this->{$property}[$index]);
        $this->{$property} = array_values($this->{$property});
        $this->clearErrorState();
    }

    private function constraintsFromForm(): string
    {
        $constraints = array_filter([
            'allowed_date' => $this->cleanRanges($this->allowedDates),
            'excluded_date' => $this->cleanRanges($this->excludedDates),
            'allowed_time' => $this->cleanRanges($this->allowedTimes),
            'excluded_time' => $this->cleanRanges($this->excludedTimes),
            'allowed_weekdays' => array_map('intval', $this->allowedWeekdays),
            'max_entries_per_day' => $this->maxEntriesPerDay,
            'max_entries' => $this->maxEntries,
            'max_duration_per_session' => $this->maxDurationPerSession,
            'max_duration' => $this->maxDuration,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return json_encode($constraints, JSON_UNESCAPED_UNICODE);
    }

    private function hasStructuredConstraintInput(): bool
    {
        return $this->allowedDates !== []
            || $this->excludedDates !== []
            || $this->allowedTimes !== []
            || $this->excludedTimes !== []
            || $this->allowedWeekdays !== []
            || $this->maxEntriesPerDay !== null
            || $this->maxEntries !== null
            || $this->maxDurationPerSession !== null
            || $this->maxDuration !== null
            || $this->constraintsJson === '{}';
    }

    private function cleanRanges(array $ranges): array
    {
        return array_values(array_filter($ranges, static fn (array $range): bool => ($range['start'] ?? '') !== '' || ($range['end'] ?? '') !== ''));
    }

    private function fillConstraintForm(array $constraints): void
    {
        $this->allowedDates = $constraints['allowed_date'] ?? [];
        $this->excludedDates = $constraints['excluded_date'] ?? [];
        $this->allowedTimes = $constraints['allowed_time'] ?? [];
        $this->excludedTimes = $constraints['excluded_time'] ?? [];
        $this->allowedWeekdays = array_map('strval', $constraints['allowed_weekdays'] ?? []);
        $this->maxEntriesPerDay = $constraints['max_entries_per_day'] ?? null;
        $this->maxEntries = $constraints['max_entries'] ?? null;
        $this->maxDurationPerSession = $constraints['max_duration_per_session'] ?? null;
        $this->maxDuration = $constraints['max_duration'] ?? null;
    }

    private function resetPermissionForm(): void
    {
        $this->permissionMode = 'deny';
        $this->resetConstraintForm();
    }

    private function resetConstraintForm(): void
    {
        $this->constraintsJson = '{}';
        $this->allowedDates = [];
        $this->excludedDates = [];
        $this->allowedTimes = [];
        $this->excludedTimes = [];
        $this->allowedWeekdays = [];
        $this->maxEntriesPerDay = null;
        $this->maxEntries = null;
        $this->maxDurationPerSession = null;
        $this->maxDuration = null;
    }

    private function rangeProperty(string $type): string
    {
        return match ($type) {
            'allowed_date' => 'allowedDates',
            'excluded_date' => 'excludedDates',
            'allowed_time' => 'allowedTimes',
            'excluded_time' => 'excludedTimes',
            default => throw new \InvalidArgumentException('Loại khoảng thời gian không hợp lệ.'),
        };
    }

    private function target(): Department|PersonType|Person
    {
        return match ($this->targetType) {
            'department' => Department::findOrFail($this->targetId),
            'role' => PersonType::findOrFail($this->targetId),
            default => Person::findOrFail($this->targetId),
        };
    }

    private function validatedConstraints(): array
    {
        $constraints = json_decode($this->constraintsJson, true);

        if (! is_array($constraints) || array_diff(array_keys($constraints), [
            'allowed_date', 'excluded_date', 'allowed_time', 'excluded_time', 'allowed_weekdays',
            'max_entries_per_day', 'max_entries', 'max_duration_per_session', 'max_duration',
        ]) !== []) {
            $this->addError('constraintsJson', 'JSON chứa khóa điều kiện không được hỗ trợ.');
            throw ValidationException::withMessages($this->getErrorBag()->toArray());
        }

        foreach (['allowed_date', 'excluded_date'] as $key) {
            $this->validateRanges($constraints[$key] ?? [], $key, 'd/m/Y', $this->constraintErrorField($key));
        }

        foreach (['allowed_time', 'excluded_time'] as $key) {
            $this->validateRanges($constraints[$key] ?? [], $key, 'H:i', $this->constraintErrorField($key));
        }

        if (isset($constraints['allowed_weekdays']) && (! is_array($constraints['allowed_weekdays']) || array_diff($constraints['allowed_weekdays'], range(1, 7)) !== [])) {
            $this->addError('allowedWeekdays', 'allowed_weekdays phải là danh sách các số từ 1 đến 7.');
        }

        foreach (['max_entries_per_day', 'max_entries', 'max_duration_per_session', 'max_duration'] as $key) {
            if (isset($constraints[$key]) && (! is_int($constraints[$key]) || $constraints[$key] < 1)) {
                $this->addError('limits', "{$key} phải là số nguyên dương.");
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            throw ValidationException::withMessages($this->getErrorBag()->toArray());
        }

        return $constraints;
    }

    private function validateRanges(array $ranges, string $key, string $format, string $errorField): void
    {
        foreach ($ranges as $range) {
            if (! is_array($range) || ! isset($range['start'], $range['end'])) {
                $this->addError($errorField, "{$key} phải gồm start và end.");
                continue;
            }

            try {
                $start = Carbon::createFromFormat($format, $range['start']);
                $startErrors = Carbon::getLastErrors();
                $end = Carbon::createFromFormat($format, $range['end']);
                $endErrors = Carbon::getLastErrors();

                if ($start === false || $end === false
                    || ($startErrors !== false && ($startErrors['warning_count'] > 0 || $startErrors['error_count'] > 0))
                    || ($endErrors !== false && ($endErrors['warning_count'] > 0 || $endErrors['error_count'] > 0))
                    || $start->format($format) !== $range['start']
                    || $end->format($format) !== $range['end']
                    || $start->greaterThan($end)) {
                    throw new \Exception();
                }
            } catch (\Throwable) {
                $this->addError($errorField, "Khoảng {$key} không hợp lệ.");
            }
        }
    }

    private function constraintErrorField(string $key): string
    {
        return match ($key) {
            'allowed_date' => 'allowedDates',
            'excluded_date' => 'excludedDates',
            'allowed_time' => 'allowedTimes',
            'excluded_time' => 'excludedTimes',
            default => 'constraintsJson',
        };
    }

    private function authorizeSystemUser(): void
    {
        abort_unless(Auth::user()?->is_system === true, 403);
    }
};
?>

<div class="flex h-screen min-h-0 flex-col overflow-hidden bg-slate-100 p-4 sm:p-6">
    @if ($showErrorDialog && $errors->isNotEmpty())
        @php
            $errorLabels = [
                'targetType' => 'Loại đối tượng',
                'targetId' => 'Đối tượng được chọn',
                'permissionMode' => 'Mức quyền',
                'constraintsJson' => 'Điều kiện quyền',
                'allowedDates' => 'Ngày được phép',
                'excludedDates' => 'Ngày bị loại trừ',
                'allowedTimes' => 'Khung giờ được phép',
                'excludedTimes' => 'Khung giờ bị loại trừ',
                'allowedWeekdays' => 'Ngày trong tuần',
                'limits' => 'Giới hạn lượt và thời lượng',
            ];
        @endphp
        <div class="fixed inset-0 z-100 flex items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="permission-error-title">
            <div
                class="w-full max-w-lg rounded-lg border border-red-200 bg-white shadow-2xl"
                x-data="{
                    trapFocus(event) {
                        const focusable = [...$el.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex=\'-1\'])')];
                        if (focusable.length === 0) return;
                        const first = focusable[0];
                        const last = focusable[focusable.length - 1];

                        if (event.shiftKey && document.activeElement === first) {
                            event.preventDefault();
                            last.focus();
                        } else if (!event.shiftKey && document.activeElement === last) {
                            event.preventDefault();
                            first.focus();
                        }
                    }
                }"
                x-init="$nextTick(() => $el.querySelector('[autofocus]')?.focus())"
                x-on:keydown.tab="trapFocus($event)"
                x-on:keydown.escape.window="$wire.closeErrorDialog()">
                <div class="flex items-start gap-3 border-b border-red-100 bg-red-50 px-5 py-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-red-100 text-lg font-bold text-red-700" aria-hidden="true">!</div>
                    <div>
                        <h2 id="permission-error-title" class="font-semibold text-red-900">Có lỗi cần sửa</h2>
                        <p class="mt-1 text-sm text-red-700">Kiểm tra các mục dưới đây trước khi lưu quyền.</p>
                    </div>
                </div>
                <div class="max-h-[60vh] overflow-y-auto px-5 py-4">
                    <div class="flex flex-col gap-4">
                        @foreach ($errors->getMessages() as $field => $messages)
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $errorLabels[$field] ?? 'Thông tin quyền' }}</p>
                                <ul class="mt-1 flex flex-col gap-1 text-sm text-red-700">
                                    @foreach ($messages as $message)
                                        <li class="flex gap-2"><span aria-hidden="true">•</span><span>{{ $message }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end border-t border-slate-200 px-5 py-4">
                    <button type="button" wire:click="closeErrorDialog" autofocus class="rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Đã hiểu, sửa lỗi</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showSuccessDialog)
        <div class="fixed inset-0 z-100 flex items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="permission-success-title">
            <div
                class="w-full max-w-md rounded-lg border border-teal-200 bg-white shadow-2xl"
                x-data="{
                    trapFocus(event) {
                        const focusable = [...$el.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex=\'-1\'])')];
                        if (focusable.length === 0) return;
                        const first = focusable[0];
                        const last = focusable[focusable.length - 1];

                        if (event.shiftKey && document.activeElement === first) {
                            event.preventDefault();
                            last.focus();
                        } else if (!event.shiftKey && document.activeElement === last) {
                            event.preventDefault();
                            first.focus();
                        }
                    }
                }"
                x-init="$nextTick(() => $el.querySelector('[autofocus]')?.focus())"
                x-on:keydown.tab="trapFocus($event)"
                x-on:keydown.escape.window="$wire.closeSuccessDialog()">
                <div class="flex flex-col items-center gap-3 px-5 py-7 text-center">
                    <div class="flex size-12 items-center justify-center rounded-full bg-teal-100 text-2xl font-bold text-teal-700" aria-hidden="true">✓</div>
                    <div>
                        <h2 id="permission-success-title" class="font-semibold text-slate-900">Đã lưu quyền thành công</h2>
                        <p class="mt-1 text-sm text-slate-500">Quyền ra vào đã được cập nhật cho đối tượng đã chọn.</p>
                    </div>
                </div>
                <div class="flex justify-end border-t border-slate-200 px-5 py-4">
                    <button type="button" wire:click="closeSuccessDialog" autofocus class="rounded-md bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Đã hiểu</button>
                </div>
            </div>
        </div>
    @endif

    <div class="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-5">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Cấp quyền ra vào</h1>
            <p class="mt-1 text-sm text-slate-500">Chọn đơn vị, vai trò hoặc một người cụ thể rồi đặt điều kiện đi qua cổng.</p>
        </div>

        <form wire:submit="savePermission" class="grid min-h-0 flex-1 grid-rows-[auto_minmax(0,1fr)] gap-0 rounded-lg border border-slate-200 bg-white shadow-sm lg:grid-rows-[minmax(0,1fr)] lg:grid-cols-[minmax(17rem,0.7fr)_minmax(0,1.3fr)]">
            <section @class([
                'flex flex-col gap-5 bg-slate-50 p-5 lg:border-r lg:border-slate-200',
                'ring-2 ring-inset ring-red-400' => $this->hasErrorHighlight('target'),
            ])>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Bước 1</p>
                    <h2 class="mt-1 font-semibold text-slate-900">Chọn đối tượng</h2>
                    <p class="mt-1 text-sm leading-5 text-slate-500">Mọi thiết lập bên phải sẽ được áp dụng cho lựa chọn này.</p>
                </div>
                <div class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                    <span>Loại đối tượng</span>
                    <div class="grid grid-cols-3 gap-1 rounded-md border border-slate-200 bg-white p-1">
                        @foreach (['department' => 'Đơn vị', 'role' => 'Vai trò', 'person' => 'Người'] as $type => $label)
                            <button type="button" wire:click="$set('targetType', '{{ $type }}')" @class([
                                'rounded px-2 py-2 text-xs font-semibold transition',
                                'bg-slate-900 text-white' => $targetType === $type,
                                'text-slate-500 hover:bg-slate-100' => $targetType !== $type,
                            ])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <label class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                    <span class="flex items-center justify-between gap-2">
                        <span>{{ $targetType === 'department' ? 'Chọn đơn vị' : ($targetType === 'role' ? 'Chọn vai trò' : 'Chọn người') }}</span>
                        <span class="text-xs font-normal text-slate-400">{{ $this->visibleTargets->count() }} kết quả</span>
                    </span>
                    <span class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400" aria-hidden="true">⌕</span>
                        <input wire:model.live.debounce.250ms="targetSearch" type="search" placeholder="Tìm theo tên hoặc mã..." class="w-full rounded-md border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                    </span>
                </label>

                <div class="flex max-h-100 flex-col gap-2 overflow-y-auto pr-1" aria-label="Danh sách đối tượng">
                    @forelse ($this->visibleTargets as $target)
                        @php
                            $targetName = $targetType === 'department' ? $target->name : ($targetType === 'role' ? $target->name : $target->full_name);
                            $targetCode = $targetType === 'role' ? null : $target->code;
                            $hasPermission = $target->permission !== null;
                        @endphp
                        <button type="button" wire:click="selectTarget({{ $target->id }})" wire:key="target-{{ $targetType }}-{{ $target->id }}" @class([
                            'flex w-full items-center gap-3 rounded-md border p-3 text-left transition',
                            'border-teal-600 bg-teal-50 ring-1 ring-teal-600' => $targetId === $target->id,
                            'border-slate-200 bg-white hover:border-teal-300 hover:bg-white' => $targetId !== $target->id,
                        ])>
                            <span @class([
                                'flex size-9 shrink-0 items-center justify-center rounded-md text-xs font-bold',
                                'bg-teal-700 text-white' => $targetId === $target->id,
                                'bg-slate-100 text-slate-600' => $targetId !== $target->id,
                            ])>{{ strtoupper(mb_substr($targetName, 0, 1)) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-800">{{ $targetName }}</span>
                                @if ($targetCode)<span class="mt-0.5 block truncate text-xs text-slate-500">{{ $targetCode }}</span>@endif
                            </span>
                            <span @class([
                                'shrink-0 text-[11px] font-semibold',
                                'text-teal-700' => $hasPermission,
                                'text-slate-400' => ! $hasPermission,
                            ])>{{ $hasPermission ? 'Đã cấu hình' : 'Chưa cấp quyền' }}</span>
                        </button>
                    @empty
                        <div class="rounded-md border border-dashed border-slate-300 px-4 py-8 text-center">
                            <p class="text-sm font-semibold text-slate-700">Không tìm thấy đối tượng</p>
                            <p class="mt-1 text-xs text-slate-500">Thử tìm bằng tên hoặc mã khác.</p>
                        </div>
                    @endforelse
                </div>
                @error('targetId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </section>

            <section
                x-data="{
                    clientErrors: {},
                    formatInput(event, type) {
                        const input = event.target;
                        const limit = type === 'date' ? 8 : 4;
                        let digits = input.value.replace(/\D/g, '').slice(0, limit);
                        let formatted = digits;

                        if (type === 'date') {
                            if (digits.length >= 2 && (Number(digits.slice(0, 2)) < 1 || Number(digits.slice(0, 2)) > 31)) {
                                digits = digits.slice(0, 1);
                            }

                            if (digits.length >= 4 && (Number(digits.slice(2, 4)) < 1 || Number(digits.slice(2, 4)) > 12)) {
                                digits = `${digits.slice(0, 2)}${digits.slice(2, 3)}`;
                            }

                            if (digits.length > 4) {
                                formatted = `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
                            } else if (digits.length > 2) {
                                formatted = `${digits.slice(0, 2)}/${digits.slice(2)}`;
                            }
                        } else if (digits.length > 2) {
                            formatted = `${digits.slice(0, 2)}:${digits.slice(2)}`;
                        }

                        if (input.value !== formatted) {
                            input.value = formatted;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    },
                    parseDate(value) {
                        const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                        if (!match) return null;

                        const date = new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
                        return date.getFullYear() === Number(match[3])
                            && date.getMonth() === Number(match[2]) - 1
                            && date.getDate() === Number(match[1]) ? date : null;
                    },
                    parseTime(value) {
                        const match = value.match(/^(\d{2}):(\d{2})$/);
                        if (!match || Number(match[1]) > 23 || Number(match[2]) > 59) return null;

                        return Number(match[1]) * 60 + Number(match[2]);
                    },
                    validateRange(area, type, range) {
                        const start = range.querySelector('[data-range-start]').value.trim();
                        const end = range.querySelector('[data-range-end]').value.trim();
                        let message = '';

                        if (start === '' && end === '') {
                            this.clientErrors[area] = '';
                            return;
                        }

                        if (start === '' || end === '') {
                            message = 'Hãy nhập đủ thời điểm bắt đầu và kết thúc.';
                        } else if (type === 'date' && (!this.parseDate(start) || !this.parseDate(end))) {
                            message = 'Ngày phải có định dạng dd/mm/yyyy.';
                        } else if (type === 'time' && (this.parseTime(start) === null || this.parseTime(end) === null)) {
                            message = 'Giờ phải có định dạng hh:mm.';
                        } else if (type === 'date' && this.parseDate(start) > this.parseDate(end)) {
                            message = 'Thời điểm bắt đầu không được sau thời điểm kết thúc.';
                        } else if (type === 'time' && this.parseTime(start) > this.parseTime(end)) {
                            message = 'Giờ bắt đầu không được sau giờ kết thúc.';
                        }

                        this.clientErrors[area] = message;
                    },
                    validateNumber(area, value) {
                        this.clientErrors[area] = value !== '' && Number(value) < 1 ? 'Giá trị phải là số nguyên dương.' : '';
                    }
                }"
                x-init="$watch(() => $wire.targetType + ':' + ($wire.targetId ?? ''), () => { clientErrors = {}; })"
                class="flex min-h-0 min-w-0 flex-col gap-5 overflow-hidden p-5">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Bước 2</p>
                    <h2 class="mt-1 font-semibold text-slate-900">Thiết lập quyền</h2>
                    <p class="mt-1 text-sm leading-5 text-slate-500">Tạo điều kiện bằng biểu mẫu. Bạn có thể kết hợp nhiều loại điều kiện.</p>
                </div>

                <div @class([
                    'grid gap-3 rounded-md sm:grid-cols-3',
                    'ring-2 ring-red-400 ring-offset-2' => $this->hasErrorHighlight('mode'),
                ])>
                    @foreach (['deny' => ['Từ chối tất cả', 'Không được qua cổng'], 'unrestricted' => ['Cho phép mọi lúc', 'Không giới hạn'], 'constrained' => ['Theo điều kiện', 'Thiết lập chi tiết']] as $value => [$label, $description])
                        <label class="flex cursor-pointer flex-col gap-1 rounded-md border p-3 transition has-checked:border-teal-600 has-checked:bg-teal-50">
                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-800"><input type="radio" wire:model.live="permissionMode" value="{{ $value }}" class="text-teal-700 focus:ring-teal-600">{{ $label }}</span>
                            <span class="pl-6 text-xs text-slate-500">{{ $description }}</span>
                        </label>
                    @endforeach
                </div>

                @if ($permissionMode === 'constrained')
                    <div @class([
                        'grid min-h-0 flex-1 gap-5 overflow-y-auto pr-1 xl:grid-cols-2 pt-1 pl-1',
                        'rounded-md ring-2 ring-red-400 ring-offset-2' => $this->hasErrorHighlight('constraints'),
                    ])>
                        <section @class([
                            'flex flex-col gap-3 rounded-md border border-slate-200 p-4',
                            'ring-2 ring-red-400 ring-offset-2' => $this->hasErrorHighlight('allowedDates'),
                        ]) x-bind:class="clientErrors.allowedDates ? 'ring-2 ring-red-400 ring-offset-2' : ''">
                            <div class="flex items-start justify-between gap-3">
                                <div><h3 class="font-semibold text-slate-900">Ngày được phép</h3><p class="text-xs text-slate-500">Chỉ cho phép trong các khoảng ngày này.</p></div>
                                <button type="button" wire:click="addRange('allowed_date')" class="shrink-0 rounded-md border border-teal-200 px-2.5 py-1.5 text-xs font-semibold text-teal-700 hover:bg-teal-50">+ Thêm khoảng</button>
                            </div>
                            @forelse ($allowedDates as $index => $range)
                                <div wire:key="allowed-date-{{ $index }}" data-range data-index="{{ $index }}" class="grid grid-cols-[1fr_1fr_auto] items-end gap-2">
                                    <label class="text-xs font-medium text-slate-600">Từ<input data-range-start inputmode="numeric" type="text" wire:model="allowedDates.{{ $index }}.start" x-on:input="formatInput($event, 'date'); validateRange('allowedDates', 'date', $el.closest('[data-range]'))" placeholder="dd/mm/yyyy" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm text-slate-900"></label>
                                    <label class="text-xs font-medium text-slate-600">Đến<input data-range-end inputmode="numeric" type="text" wire:model="allowedDates.{{ $index }}.end" x-on:input="formatInput($event, 'date'); validateRange('allowedDates', 'date', $el.closest('[data-range]'))" placeholder="dd/mm/yyyy" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm text-slate-900"></label>
                                    <button type="button" x-on:click="clientErrors.allowedDates = ''" wire:click="removeRange('allowed_date', {{ $index }})" aria-label="Xóa khoảng ngày" class="mb-1 rounded-md px-2 py-2 text-slate-400 hover:bg-red-50 hover:text-red-600">&times;</button>
                                </div>
                            @empty
                                <p class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">Không giới hạn theo ngày.</p>
                            @endforelse
                            @error('allowedDates') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            <span x-cloak x-show="clientErrors.allowedDates" x-text="clientErrors.allowedDates" class="text-xs text-red-600"></span>
                        </section>

                        <section @class([
                            'flex flex-col gap-3 rounded-md border border-slate-200 p-4',
                            'ring-2 ring-red-400 ring-offset-2' => $this->hasErrorHighlight('excludedDates'),
                        ]) x-bind:class="clientErrors.excludedDates ? 'ring-2 ring-red-400 ring-offset-2' : ''">
                            <div class="flex items-start justify-between gap-3">
                                <div><h3 class="font-semibold text-slate-900">Ngày bị loại trừ</h3><p class="text-xs text-slate-500">Không cho phép trong các khoảng ngày này.</p></div>
                                <button type="button" wire:click="addRange('excluded_date')" class="shrink-0 rounded-md border border-amber-200 px-2.5 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50">+ Thêm khoảng</button>
                            </div>
                            @forelse ($excludedDates as $index => $range)
                                <div wire:key="excluded-date-{{ $index }}" data-range data-index="{{ $index }}" class="grid grid-cols-[1fr_1fr_auto] items-end gap-2">
                                    <label class="text-xs font-medium text-slate-600">Từ<input data-range-start inputmode="numeric" type="text" wire:model="excludedDates.{{ $index }}.start" x-on:input="formatInput($event, 'date'); validateRange('excludedDates', 'date', $el.closest('[data-range]'))" placeholder="dd/mm/yyyy" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm text-slate-900"></label>
                                    <label class="text-xs font-medium text-slate-600">Đến<input data-range-end inputmode="numeric" type="text" wire:model="excludedDates.{{ $index }}.end" x-on:input="formatInput($event, 'date'); validateRange('excludedDates', 'date', $el.closest('[data-range]'))" placeholder="dd/mm/yyyy" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm text-slate-900"></label>
                                    <button type="button" x-on:click="clientErrors.excludedDates = ''" wire:click="removeRange('excluded_date', {{ $index }})" aria-label="Xóa khoảng ngày" class="mb-1 rounded-md px-2 py-2 text-slate-400 hover:bg-red-50 hover:text-red-600">&times;</button>
                                </div>
                            @empty
                                <p class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">Không có ngày loại trừ.</p>
                            @endforelse
                            @error('excludedDates') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            <span x-cloak x-show="clientErrors.excludedDates" x-text="clientErrors.excludedDates" class="text-xs text-red-600"></span>
                        </section>

                        @foreach ([['allowed_time', 'allowedTimes', 'Khung giờ được phép', 'Chỉ cho phép trong các khung giờ này.'], ['excluded_time', 'excludedTimes', 'Khung giờ bị loại trừ', 'Không cho phép trong các khung giờ này.']] as [$type, $property, $title, $description])
                            <section @class([
                                'flex flex-col gap-3 rounded-md border border-slate-200 p-4',
                                'ring-2 ring-red-400 ring-offset-2' => $this->hasErrorHighlight($property),
                            ]) x-bind:class="clientErrors.{{ $property }} ? 'ring-2 ring-red-400 ring-offset-2' : ''">
                                <div class="flex items-start justify-between gap-3">
                                    <div><h3 class="font-semibold text-slate-900">{{ $title }}</h3><p class="text-xs text-slate-500">{{ $description }}</p></div>
                                    <button type="button" wire:click="addRange('{{ $type }}')" class="shrink-0 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">+ Thêm khoảng</button>
                                </div>
                                @forelse ($$property as $index => $range)
                                    <div wire:key="{{ $type }}-{{ $index }}" data-range data-index="{{ $index }}" class="grid grid-cols-[1fr_1fr_auto] items-end gap-2">
                                        <label class="text-xs font-medium text-slate-600">Từ<input data-range-start inputmode="numeric" type="text" wire:model="{{ $property }}.{{ $index }}.start" x-on:input="formatInput($event, 'time'); validateRange('{{ $property }}', 'time', $el.closest('[data-range]'))" placeholder="hh:mm" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm text-slate-900"></label>
                                        <label class="text-xs font-medium text-slate-600">Đến<input data-range-end inputmode="numeric" type="text" wire:model="{{ $property }}.{{ $index }}.end" x-on:input="formatInput($event, 'time'); validateRange('{{ $property }}', 'time', $el.closest('[data-range]'))" placeholder="hh:mm" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm text-slate-900"></label>
                                        <button type="button" x-on:click="clientErrors.{{ $property }} = ''" wire:click="removeRange('{{ $type }}', {{ $index }})" aria-label="Xóa khung giờ" class="mb-1 rounded-md px-2 py-2 text-slate-400 hover:bg-red-50 hover:text-red-600">&times;</button>
                                    </div>
                                @empty
                                    <p class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">Không giới hạn theo giờ.</p>
                                @endforelse
                                @error($property) <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                <span x-cloak x-show="clientErrors.{{ $property }}" x-text="clientErrors.{{ $property }}" class="text-xs text-red-600"></span>
                            </section>
                        @endforeach

                        <section @class([
                            'flex flex-col gap-3 rounded-md border border-slate-200 p-4 xl:col-span-2',
                            'ring-2 ring-red-400 ring-offset-2' => $this->hasErrorHighlight('allowedWeekdays'),
                        ])>
                            <div><h3 class="font-semibold text-slate-900">Ngày trong tuần</h3><p class="text-xs text-slate-500">Chọn ít nhất một ngày nếu muốn giới hạn lịch.</p></div>
                            <div class="grid grid-cols-4 gap-2 sm:grid-cols-7">
                                @foreach ([1 => 'CN', 2 => 'T2', 3 => 'T3', 4 => 'T4', 5 => 'T5', 6 => 'T6', 7 => 'T7'] as $day => $label)
                                    <label class="flex cursor-pointer flex-col items-center gap-1 rounded-md border border-slate-200 px-2 py-2 text-xs has-checked:border-teal-600 has-checked:bg-teal-50"><input type="checkbox" wire:model="allowedWeekdays" value="{{ $day }}" class="text-teal-700 focus:ring-teal-600"><span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                            @error('allowedWeekdays') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </section>

                        <section @class([
                            'grid gap-3 rounded-md border border-slate-200 p-4 sm:grid-cols-2 xl:col-span-2 xl:grid-cols-4',
                            'ring-2 ring-red-400 ring-offset-2' => $this->hasErrorHighlight('limits'),
                        ]) x-bind:class="clientErrors.limits ? 'ring-2 ring-red-400 ring-offset-2' : ''">
                            @foreach ([['maxEntriesPerDay', 'Lượt vào mỗi ngày'], ['maxEntries', 'Tổng lượt vào'], ['maxDurationPerSession', 'Thời lượng mỗi lượt (phút)'], ['maxDuration', 'Tổng thời lượng (phút)']] as [$field, $label])
                                <label class="flex flex-col gap-1 text-xs font-medium text-slate-600">{{ $label }}<input type="number" min="1" wire:model="{{ $field }}" x-on:input="validateNumber('limits', $event.target.value)" class="rounded-md border border-slate-300 px-2.5 py-2 text-sm text-slate-900" placeholder="Không giới hạn"></label>
                            @endforeach
                            @error('limits') <span class="text-xs text-red-600 xl:col-span-4">{{ $message }}</span> @enderror
                            <span x-cloak x-show="clientErrors.limits" x-text="clientErrors.limits" class="text-xs text-red-600 sm:col-span-2 xl:col-span-4"></span>
                        </section>
                    </div>
                @endif

                @error('constraintsJson') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                <div class="flex items-center justify-between gap-3">
                    @if (session('permissionSaved')) <span class="text-sm font-medium text-teal-700">{{ session('permissionSaved') }}</span> @else <span></span> @endif
                    <button type="submit" class="rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Lưu quyền</button>
                </div>
            </section>
        </form>
    </div>
</div>