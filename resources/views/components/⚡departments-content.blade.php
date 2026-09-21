<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new class extends Component {
    use WithFileUploads;

    public $name = '';
    public $parent_code = '';
    public $is_active = true;
    private $parent_id = null;
    public $departments = null;
    public $map = [];
    public $selectedDepartmentId = null;
    public $editingDepartmentId = null;
    public $editingDepartmentName = '';
    public $departmentSpreadsheet;
    public $bulkImportErrors = [];
    public $bulkImportSuccess = '';
    public array $creationLogs = [];

    public function mount()
    {
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

    public function addDepartment(?string $parentCode = null): void
    {
        $this->parent_code = $parentCode ?? '';

        $this->validate([
            'name' => 'required|max:150',
            'parent_code' => 'max:50',
        ]);
        if ($this->parent_code !== '' && mb_strlen($this->parent_code) + 4 > 50) {
            $this->addError('parent_code', 'Không thể tạo thêm đơn vị con vì mã đơn vị tự động sẽ vượt quá 50 ký tự.');
            return;
        }
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
        $this->recordDepartmentCreation($department);
        $this->reset(['name', 'is_active']);
    }

    private function createDepartmentsFromRows(array $rows): void
    {
        try {
            $createdCount = 0;
            $createdDepartments = [];

            DB::transaction(function () use ($rows, &$createdCount, &$createdDepartments): void {
                $departmentIdsByCode = Department::pluck('id', 'code')->all();

                foreach ($rows as $row) {
                    $parentId = null;

                    if ($row['parent_code'] !== '') {
                        $parentId = $departmentIdsByCode[$row['parent_code']] ?? null;

                        if ($parentId === null) {
                            throw ValidationException::withMessages([
                                'departmentSpreadsheet' => "Dòng {$row['line']}: mã đơn vị cha không tồn tại hoặc chưa được nhập ở dòng trước.",
                            ]);
                        }

                        if (mb_strlen($row['parent_code']) + 4 > 50) {
                            throw ValidationException::withMessages([
                                'departmentSpreadsheet' => "Dòng {$row['line']}: không thể tạo thêm đơn vị con vì mã đơn vị tự động sẽ vượt quá 50 ký tự.",
                            ]);
                        }
                    }

                    $department = Department::create([
                        'name' => $row['name'],
                        'parent_id' => $parentId,
                        'is_active' => true,
                    ]);

                    $departmentIdsByCode[$department->code] = $department->id;
                    $createdDepartments[] = $department;
                    $createdCount++;
                }
            });

            $this->loadDepartments();
            foreach ($createdDepartments as $department) {
                $this->recordDepartmentCreation($department);
            }
            $this->bulkImportSuccess = "Đã thêm {$createdCount} đơn vị.";
        } catch (ValidationException $exception) {
            $this->bulkImportErrors = collect($exception->errors())->flatten()->values()->all();
        }
    }

    public function importDepartmentSpreadsheet(): void
    {
        $this->bulkImportErrors = [];
        $this->bulkImportSuccess = '';

        $this->validate([
            'departmentSpreadsheet' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:5120'],
        ]);

        try {
            $rows = $this->readSpreadsheetRows($this->departmentSpreadsheet->getRealPath());

            if (count($rows) < 2) {
                throw new \RuntimeException('Tệp Excel cần có hàng tiêu đề và ít nhất một đơn vị.');
            }

            $headers = array_map(fn ($header) => mb_strtolower(trim($header)), $rows[0]);

            if (array_diff(['tên đơn vị', 'mã đơn vị cha'], $headers) !== []) {
                throw new \RuntimeException('Hàng tiêu đề phải có: Tên đơn vị, Mã đơn vị cha.');
            }

            $headerIndexes = array_flip($headers);
            $departmentRows = [];

            foreach (array_slice($rows, 1) as $index => $row) {
                $name = trim($row[$headerIndexes['tên đơn vị']] ?? '');
                $parentCode = trim($row[$headerIndexes['mã đơn vị cha']] ?? '');
                $parentCode = str_contains($parentCode, ' - ')
                    ? explode(' - ', $parentCode, 2)[0]
                    : $parentCode;

                if ($name === '' && $parentCode === '') {
                    continue;
                }

                $departmentRows[] = [
                    'line' => $index + 2,
                    'name' => $name,
                    'parent_code' => $parentCode,
                ];
            }

            if ($departmentRows === []) {
                throw new \RuntimeException('Tệp Excel cần có ít nhất một đơn vị.');
            }

            foreach ($departmentRows as $departmentRow) {
                if ($departmentRow['name'] === '') {
                    $this->bulkImportErrors[] = "Dòng {$departmentRow['line']}: tên đơn vị không được bỏ trống.";
                } elseif (mb_strlen($departmentRow['name']) > 150) {
                    $this->bulkImportErrors[] = "Dòng {$departmentRow['line']}: tên đơn vị không được vượt quá 150 ký tự.";
                }

                if (mb_strlen($departmentRow['parent_code']) > 50) {
                    $this->bulkImportErrors[] = "Dòng {$departmentRow['line']}: mã đơn vị cha không được vượt quá 50 ký tự.";
                }
            }

            if ($this->bulkImportErrors !== []) {
                return;
            }

            $this->createDepartmentsFromRows($departmentRows);
            $this->reset('departmentSpreadsheet');
        } catch (ValidationException $exception) {
            $this->bulkImportErrors = collect($exception->errors())->flatten()->values()->all();
        } catch (\RuntimeException $exception) {
            $this->bulkImportErrors = [$exception->getMessage()];
        }
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $departments = $this->departments
            ->map(fn (Department $department) => $department->code.' - '.$department->name)
            ->values();
        $templatePath = tempnam(sys_get_temp_dir(), 'department-template-');

        if ($templatePath === false) {
            throw new \RuntimeException('Không thể tạo tệp mẫu Excel.');
        }

        $zip = new \ZipArchive;

        if ($zip->open($templatePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($templatePath);
            throw new \RuntimeException('Không thể tạo tệp mẫu Excel.');
        }

        $lastDepartmentRow = max(2, $departments->count() + 1);
        $departmentRows = '';

        foreach ($departments as $index => $code) {
            $rowNumber = $index + 2;
            $departmentRows .= '<row r="'.$rowNumber.'">'.$this->excelCell('A'.$rowNumber, $code).'</row>';
        }

        $departmentExample = $departments->first() ?? '';
        $departmentsSheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$this->excelCell('A1', 'Mã đơn vị - Tên đơn vị').'</row>'.$departmentRows.'</sheetData></worksheet>';
        $mainSheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="42" customWidth="1"/><col min="2" max="2" width="57" customWidth="1"/></cols><sheetData>'
            .'<row r="1">'.$this->excelCell('A1', 'Tên đơn vị').$this->excelCell('B1', 'Mã đơn vị cha').'</row>'
            .'<row r="2">'.$this->excelCell('A2', 'Phòng Tài chính').$this->excelCell('B2', $departmentExample).'</row>'
            .'</sheetData><dataValidations count="1"><dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="B2:B1000"><formula1>&apos;CodeList&apos;!$A$2:$A$'.$lastDepartmentRow.'</formula1></dataValidation></dataValidations></worksheet>';

        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Departments" sheetId="1" r:id="rId1"/><sheet name="CodeList" sheetId="2" state="hidden" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $mainSheet);
        $zip->addFromString('xl/worksheets/sheet2.xml', $departmentsSheet);
        $zip->close();

        return response()->download($templatePath, 'departments-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function excelCell(string $coordinate, string $value): string
    {
        $escapedValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<c r="'.$coordinate.'" t="inlineStr"><is><t>'.$escapedValue.'</t></is></c>';
    }

    /** @return array<int, array<int, string>> */
    private function readSpreadsheetRows(string $path): array
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Không thể mở tệp Excel.');
        }

        try {
            $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

            if ($worksheetXml === false) {
                throw new \RuntimeException('Tệp Excel không có trang tính đầu tiên.');
            }

            $sharedStrings = $this->sharedStrings($zip->getFromName('xl/sharedStrings.xml'));
            $worksheet = simplexml_load_string($worksheetXml, \SimpleXMLElement::class, LIBXML_NONET);

            if ($worksheet === false) {
                throw new \RuntimeException('Không thể đọc nội dung tệp Excel.');
            }

            $rows = [];

            foreach ($worksheet->xpath('//*[local-name()="row"]') ?: [] as $row) {
                $values = [];

                foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    $columnIndex = $this->spreadsheetColumnIndex((string) $cell['r']);
                    $type = (string) $cell['t'];
                    $valueNode = $cell->xpath('./*[local-name()="v"]')[0] ?? null;
                    $value = $valueNode === null ? '' : (string) $valueNode;

                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    }

                    if ($type === 'inlineStr') {
                        $value = implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]') ?: []));
                    }

                    $values[$columnIndex] = trim($value);
                }

                if ($values !== []) {
                    ksort($values);
                    $rows[] = $values;
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    private function sharedStrings(string|false $xml): array
    {
        if ($xml === false) {
            return [];
        }

        $sharedStrings = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);

        if ($sharedStrings === false) {
            return [];
        }

        return array_map(
            fn ($string) => implode('', array_map('strval', $string->xpath('.//*[local-name()="t"]') ?: [])),
            $sharedStrings->xpath('//*[local-name()="si"]') ?: [],
        );
    }

    private function spreadsheetColumnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/', $reference, $matches);
        $index = 0;

        foreach (str_split($matches[0] ?? '') as $character) {
            $index = ($index * 26) + ord($character) - 64;
        }

        return max(0, $index - 1);
    }

    private function recordDepartmentCreation(Department $department): void
    {
        $this->creationLogs[] = [
            'created_at' => now()->format('H:i:s'),
            'name' => $department->name,
            'code' => $department->code,
        ];
    }

    public function startRenamingDepartment(int $departmentId): void
    {
        $department = $this->departments->firstWhere('id', $departmentId);

        if (!$department) {
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

        if (!$department) {
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

        if (!$department) {
            return;
        }

        $departmentIds = [$department->id];

        if (!$isActive || $includeChildren) {
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

<div class="h-full bg-slate-100 p-4 sm:p-6" x-data="{
    selectedDepartmentId: @js($selectedDepartmentId),
    parentCode: @js($parent_code),
    selectParent(departmentId, departmentCode) {
        if (departmentId === this.selectedDepartmentId) {
            this.selectedDepartmentId = null;
            this.parentCode = '';
            return;
        }

        this.selectedDepartmentId = departmentId;
        this.parentCode = departmentCode;
    }
}">
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Đơn vị</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Tạo nhóm người để áp dụng quyền ra vào chi tiết cho từng
                    nhóm.</p>
            </div>
        </div>
        <div
            class="grid min-h-128 grid-cols-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:grid-cols-[minmax(20rem,0.85fr)_minmax(0,1.15fr)]">
            <section class="bg-slate-50 max-h-full" x-data="{ bulkMode: false }">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Thêm mới đơn vị</h2>
                    <p class="mt-1 text-sm text-slate-500">Tạo một đơn vị trong hệ thống.</p>
                </div>
                <div class="flex gap-1 border-b border-slate-200 px-5 pt-4">
                    <button type="button" x-on:click="bulkMode = false"
                        class="border-b-2 px-3 pb-3 text-sm font-semibold transition"
                        :class="bulkMode ? 'border-transparent text-slate-500 hover:text-slate-800' :
                            'border-teal-700 text-teal-700'">
                        Thêm từng đơn vị
                    </button>
                    <button type="button" x-on:click="bulkMode = true"
                        class="border-b-2 px-3 pb-3 text-sm font-semibold transition"
                        :class="bulkMode ? 'border-teal-700 text-teal-700' :
                            'border-transparent text-slate-500 hover:text-slate-800'">
                        Nhập nhiều đơn vị
                    </button>
                </div>
                <form x-show="!bulkMode" x-on:submit.prevent="$wire.addDepartment(parentCode)" class="flex flex-col gap-5 p-5">
                    <label class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                        <span>Tên đơn vị</span>
                        <input
                            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"
                            type="text" placeholder="Nhập tên đơn vị" wire:model="name">
                        @error('name')
                            @if ($message == 'The name field is required.')
                                <span class="font-normal text-red-600">Tên đơn vị không được bỏ trống.</span>
                            @else
                                <span class="font-normal text-red-600">{{ $message }}</span>
                            @endif
                        @enderror
                    </label>
                    <label class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                        <span>Mã đơn vị cha</span>
                        <div class="flex items-center gap-2">
                            <select
                                class="block min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"
                                x-model="parentCode"
                                x-on:change="selectedDepartmentId = Number($event.target.selectedOptions[0]?.dataset.departmentId) || null">
                                <option value="">Không có đơn vị cha</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->code }}" data-department-id="{{ $department->id }}">
                                        {{ $department->code }} - {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" x-on:click="selectedDepartmentId = null; parentCode = ''"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white font-semibold text-slate-500 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600"
                                aria-label="Xóa đơn vị cha" title="Xóa đơn vị cha">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        @error('parent_code')
                            <span class="font-normal text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                    <button
                        class="mt-1 inline-flex w-full items-center justify-center rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2"
                        type="submit">Thêm đơn vị</button>
                </form>
                <div x-show="bulkMode" class="flex flex-col gap-4 p-5">
                    <div class="flex flex-col gap-3 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Nhập từ Excel</h3>
                                <p class="mt-1 text-xs text-slate-600">Dùng mẫu Excel để chọn mã đơn vị cha từ danh sách có sẵn.</p>
                            </div>
                            <button type="button" wire:click="downloadTemplate"
                                class="inline-flex items-center justify-center rounded-md border border-teal-700 bg-white px-3 py-2 text-sm font-semibold text-teal-700 transition hover:bg-teal-100">
                                Tải mẫu Excel
                            </button>
                        </div>
                        <input type="file" wire:model="departmentSpreadsheet" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            class="block w-full rounded-md border px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-md file:border file:border-slate-400 file:px-3 file:py-1.5 file:font-semibold
                                {{ $errors->has('departmentSpreadsheet') ? 'border-red-600 bg-red-50 file:bg-red-100 file:text-red-800' : ($departmentSpreadsheet ? 'border-green-600 bg-green-50 file:bg-green-100 file:text-green-800' : 'border-black bg-white file:bg-slate-100 file:text-slate-800') }}">
                        @error('departmentSpreadsheet')
                            <span class="text-sm font-normal text-red-600">{{ $message }}</span>
                        @enderror
                        <button type="button" wire:click="importDepartmentSpreadsheet"
                            class="inline-flex w-full items-center justify-center rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
                            Nhập tệp Excel
                        </button>
                    </div>
                    @if ($bulkImportErrors !== [])
                        <div class="flex flex-col gap-1 text-sm text-red-600">
                            @foreach ($bulkImportErrors as $error)
                                <span>{{ $error }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if ($bulkImportSuccess !== '')
                        <p class="text-sm text-teal-700">{{ $bulkImportSuccess }}</p>
                    @endif
                </div>
                <section class="border-t border-slate-200 px-5 py-4" aria-labelledby="department-creation-log-title">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 id="department-creation-log-title" class="font-semibold text-slate-900">Nhật ký tạo đơn vị</h3>
                            <p class="mt-1 text-sm text-slate-500">Các đơn vị vừa được tạo trong phiên này.</p>
                        </div>
                        <span class="rounded-full bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-600">
                            {{ count($creationLogs) }}
                        </span>
                    </div>
                    @if ($creationLogs === [])
                        <p class="mt-4 text-sm text-slate-500">Chưa có hoạt động.</p>
                    @else
                        <div class="mt-4 flex max-h-48 flex-col overflow-y-auto">
                            @foreach (array_reverse($creationLogs) as $log)
                                <div class="flex items-center gap-2 border-b border-slate-200 px-1 py-2 text-sm {{ $loop->first ? 'bg-teal-50 font-semibold text-teal-700' : 'text-slate-700' }}">
                                    <p class="min-w-0 truncate">
                                        Đã tạo <span class="font-semibold {{ $loop->first ? 'text-teal-900' : 'text-slate-900' }}">{{ $log['name'] }}</span>
                                        <span class="{{ $loop->first ? 'text-teal-600' : 'text-slate-500' }}">({{ $log['code'] }})</span>
                                    </p>
                                    <time class="ml-auto shrink-0 text-xs text-slate-400">{{ $log['created_at'] }}</time>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </section>
            <section class="min-w-0 border-b border-slate-200 lg:border-b-0 lg:border-l">
                <div class="border-b border-slate-200 px-5 py-4 flex flex-row justify-between items-center">
                    <div>
                        <h2 class="font-semibold text-slate-900">Danh sách đơn vị</h2>
                        <p class="mt-1 text-sm text-slate-500">Chọn một đơn vị để đặt làm đơn vị cha.</p>
                    </div>
                    <div
                        class="hidden rounded-md border border-slate-200 bg-white px-2 py-2 text-right shadow-sm sm:block">
                        <p class=" font-bold text-slate-900">{{ $departments->count() }} <span class="text-xs font-medium text-slate-500">đơn vị</span></p>
                    </div>
                </div>
                @if ($departments->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Danh sách trống</p>
                @endif
                <div class="flex max-h-[calc(100vh-14rem)] min-h-0 flex-col gap-1 overflow-auto p-4"
                    wire:key="department-list">
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
