<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new class extends Component {
	use WithFileUploads;

	public ?int $departmentId = null;
	public ?int $selectedDepartmentId = null;
	public string $username = '';
	public string $phone = '';
	public string $password = '';
	public $departments;
	public $treeDepartments;
	public array $departmentRows = [];
	public array $expandedDepartmentIds = [];
	public $users;
	public ?int $editingUserId = null;
	public string $editingUsername = '';
	public string $editingPhone = '';
	public bool $editingIsActive = true;
	public string $newPassword = '';
	public $accountSpreadsheet;
	public array $bulkImportErrors = [];
	public string $bulkImportSuccess = '';
	public array $createLogs = [];
	public array $editLogs = [];

	public function mount(): void
	{
		Gate::authorize('viewAny', User::class);

		$this->loadAccounts();
		$this->selectDepartment($this->departments->first()?->id);
	}

	public function selectDepartment(?int $departmentId): void
	{
		if ($departmentId === null || ! $this->departments->contains('id', $departmentId)) {
			return;
		}

		$this->selectedDepartmentId = $departmentId;
		$this->departmentId = $departmentId;
		$this->loadAccounts();
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

	public function saveUser(): void
	{
		$validated = $this->validate([
			'departmentId' => ['required', 'integer', 'exists:departments,id'],
			'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/D', 'unique:users,username'],
			'phone' => ['required', 'string', 'max:20'],
			'password' => ['required', 'string', 'min:8', 'max:255', 'regex:/^\S+$/'],
		]);

		$department = Department::findOrFail($validated['departmentId']);

		if (Gate::denies('create', [User::class, $department])) {
			$this->addError('departmentId', 'Bạn không có quyền tạo tài khoản cho đơn vị này. Chỉ được chọn đơn vị thuộc phạm vi quản lý của bạn.');

			return;
		}

		User::create([
			'department_id' => $department->id,
			'username' => $validated['username'],
			'phone' => $validated['phone'],
			'password' => $validated['password'],
			'is_active' => true,
		]);

		$this->reset(['username', 'phone', 'password']);
		$this->createLogs[] = [
			'created_at' => now()->format('H:i:s'),
			'message' => "Đã tạo tài khoản {$validated['username']}.",
		];
		$this->loadAccounts();
	}

	public function beginEditing(int $userId): void
	{
		$user = User::findOrFail($userId);
		Gate::authorize('update', $user);

		$this->editingUserId = $user->id;
		$this->editingUsername = $user->username;
		$this->editingPhone = $user->phone;
		$this->editingIsActive = $user->is_active;
		$this->newPassword = '';
		$this->editLogs = [];
		$this->resetValidation();
	}

	public function cancelEditing(): void
	{
		$this->reset(['editingUserId', 'editingUsername', 'editingPhone', 'newPassword']);
		$this->editingIsActive = true;
		$this->editLogs = [];
		$this->resetValidation();
	}

	public function updateUser(): void
	{
		if ($this->editingUserId === null) {
			return;
		}

		$user = User::findOrFail($this->editingUserId);
		Gate::authorize('update', $user);

		$validated = $this->validate([
			'editingPhone' => ['required', 'string', 'max:20'],
			'editingIsActive' => ['required', 'boolean'],
		]);

		$user->update([
			'phone' => $validated['editingPhone'],
			'is_active' => $validated['editingIsActive'],
		]);

		$this->addEditLog('Đã cập nhật thông tin tài khoản.');
		$this->loadAccounts();
	}

	public function resetUserPassword(): void
	{
		if ($this->editingUserId === null) {
			return;
		}

		$user = User::findOrFail($this->editingUserId);
		Gate::authorize('update', $user);

		$this->validate([
			'newPassword' => ['required', 'string', 'min:8', 'max:255', 'regex:/^\S+$/'],
		]);

		$user->update(['password' => $this->newPassword]);
		$this->newPassword = '';
		$this->addEditLog('Đã đặt lại mật khẩu.');
	}

	private function addEditLog(string $message): void
	{
		$this->editLogs[] = [
			'created_at' => now()->format('H:i:s'),
			'message' => $message,
		];
	}

	public function importAccounts(): void
	{
		Gate::authorize('create', User::class);

		$this->bulkImportErrors = [];
		$this->bulkImportSuccess = '';

		$this->validate([
			'accountSpreadsheet' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:5120'],
		]);

		try {
			$rows = $this->readSpreadsheetRows($this->accountSpreadsheet->getRealPath());
			$accounts = $this->validateSpreadsheetRows($rows);

			foreach ($accounts as $index => $account) {
				if (Gate::denies('create', [User::class, $account['department']])) {
					$this->bulkImportErrors[] = 'Dòng '.($index + 2).': bạn không có quyền tạo tài khoản cho đơn vị này.';
				}
			}

			if ($this->bulkImportErrors !== []) {
				return;
			}

			DB::transaction(function () use ($accounts): void {
				foreach ($accounts as $account) {
					Gate::authorize('create', [User::class, $account['department']]);

					User::create([
						'department_id' => $account['department']->id,
						'username' => $account['username'],
						'phone' => $account['phone'],
						'password' => $account['password'],
						'is_active' => $account['is_active'],
					]);
				}
			});
		} catch (\Illuminate\Validation\ValidationException $exception) {
			$this->bulkImportErrors = collect($exception->errors())
				->flatten()
				->values()
				->all();

			return;
		} catch (\RuntimeException $exception) {
			$this->bulkImportErrors = [$exception->getMessage()];

			return;
		}

		$createdCount = count($accounts);
		$this->reset('accountSpreadsheet');
		$this->bulkImportSuccess = "Đã tạo {$createdCount} tài khoản.";
		$this->loadAccounts();
	}

	public function downloadTemplate(): BinaryFileResponse
	{
		$departments = $this->departments
			->map(fn (Department $department) => [
				'code' => $department->code,
				'label' => $department->code.' - '.$department->name,
			])
			->values();
		$templatePath = tempnam(sys_get_temp_dir(), 'user-template-');

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

		foreach ($departments as $index => $department) {
			$rowNumber = $index + 2;
			$departmentRows .= '<row r="'.$rowNumber.'">'.$this->excelCell('A'.$rowNumber, $department['label']).'</row>';
		}

		$departmentExample = $departments->first()['label'] ?? '';
		$usersSheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="42" customWidth="1"/><col min="2" max="2" width="21" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/><col min="4" max="4" width="57" customWidth="1"/></cols><sheetData>'
			.'<row r="1">'.$this->excelCell('A1', 'Tên đăng nhập').$this->excelCell('B1', 'Mật khẩu').$this->excelCell('C1', 'Số điện thoại').$this->excelCell('D1', 'Đơn vị').'</row>'
			.'<row r="2">'.$this->excelCell('A2', 'example.user').$this->excelCell('B2', 'password123').$this->excelCell('C2', '0900000000').$this->excelCell('D2', $departmentExample).'</row>'
			.'</sheetData><dataValidations count="1"><dataValidation type="list" allowBlank="0" showErrorMessage="1" sqref="D2:D1000"><formula1>&apos;Departments&apos;!$A$2:$A$'.$lastDepartmentRow.'</formula1></dataValidation></dataValidations></worksheet>';
		$departmentsSheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$this->excelCell('A1', 'Danh sách đơn vị').'</row>'.$departmentRows.'</sheetData></worksheet>';

		$zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
		$zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
		$zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Users" sheetId="1" r:id="rId1"/><sheet name="Departments" sheetId="2" state="hidden" r:id="rId2"/></sheets></workbook>');
		$zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
		$zip->addFromString('xl/worksheets/sheet1.xml', $usersSheet);
		$zip->addFromString('xl/worksheets/sheet2.xml', $departmentsSheet);
		$zip->close();

		return response()->download($templatePath, 'users-import-template.xlsx', [
			'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		])->deleteFileAfterSend(true);
	}

	private function excelCell(string $coordinate, string $value): string
	{
		$escapedValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

		return '<c r="'.$coordinate.'" t="inlineStr"><is><t>'.$escapedValue.'</t></is></c>';
	}

	public function autoCreateMissingUsers(): void
	{
		Gate::authorize('create', User::class);

		$createdCount = 0;

		DB::transaction(function () use (&$createdCount): void {
			foreach ($this->departments as $department) {
				if (User::query()->where('department_id', $department->id)->exists()) {
					continue;
				}

				Gate::authorize('create', [User::class, $department]);
				$generatedUsername = $this->generatedUsername($department);

				User::create([
					'department_id' => $department->id,
					'username' => $generatedUsername,
					'phone' => auth()->user()->phone,
					'password' => Str::random(32),
					'is_active' => true,
				]);

				$this->createLogs[] = [
					'created_at' => now()->format('H:i:s'),
					'message' => "Đã tạo tài khoản {$generatedUsername}.",
				];
				$createdCount++;
			}
		});

		$this->loadAccounts();
	}

	private function loadAccounts(): void
	{
		$actor = auth()->user();

		$this->departments = Department::query()
			->orderBy('code')
			->get()
			->filter(fn (Department $department) => Gate::forUser($actor)->allows('create', [User::class, $department]))
			->values();
		$this->treeDepartments = $this->departmentTreeContext($actor);
		if ($this->expandedDepartmentIds === []) {
			$this->expandedDepartmentIds = $this->treeDepartments
				->filter(fn (Department $department) => $this->treeDepartments->contains('parent_id', $department->id))
				->pluck('id')
				->all();
		}
		$this->departmentRows = $this->buildDepartmentRows();

		$this->users = User::query()
			->with('department')
			->orderBy('username')
			->get()
			->filter(fn (User $user) => Gate::forUser($actor)->allows('view', $user)
				&& ($this->selectedDepartmentId === null || $user->department_id === $this->selectedDepartmentId))
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

	private function departmentTreeContext(User $actor)
	{
		$allDepartments = Department::query()->orderBy('code')->get();
		$visibleDepartmentIds = $this->departments->pluck('id')->all();

		if ($actor->is_system || $actor->department_id === null) {
			return $allDepartments;
		}

		$departmentById = $allDepartments->keyBy('id');
		$departmentId = $actor->department_id;

		while ($departmentId !== null) {
			$visibleDepartmentIds[] = $departmentId;
			$departmentId = $departmentById->get($departmentId)?->parent_id;
		}

		return $allDepartments->whereIn('id', array_unique($visibleDepartmentIds))->values();
	}

	/**
	 * @return array<int, array<int, string>>
	 */
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
					$reference = (string) $cell['r'];
					$columnIndex = $this->spreadsheetColumnIndex($reference);
					$type = (string) $cell['t'];
					$valueNode = $cell->xpath('./*[local-name()="v"]')[0] ?? null;
					$value = $valueNode === null ? '' : (string) $valueNode;

					if ($type === 's') {
						$value = $sharedStrings[(int) $value] ?? '';
					}

					if ($type === 'inlineStr') {
						$textNodes = $cell->xpath('.//*[local-name()="t"]') ?: [];
						$value = implode('', array_map('strval', $textNodes));
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

	/**
	 * @return array<int, string>
	 */
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

	/**
	 * @param array<int, array<int, string>> $rows
	 * @return array<int, array{department: Department, username: string, phone: string, password: string, is_active: bool}>
	 */
	private function validateSpreadsheetRows(array $rows): array
	{
		if (count($rows) < 2) {
			throw new \RuntimeException('Tệp Excel cần có hàng tiêu đề và ít nhất một tài khoản.');
		}

		$headers = array_map(fn ($header) => Str::lower(trim($header)), $rows[0]);
		$requiredHeaders = ['tên đăng nhập', 'số điện thoại', 'đơn vị', 'mật khẩu'];

		if (array_diff($requiredHeaders, $headers) !== []) {
			throw new \RuntimeException('Hàng tiêu đề phải có: Tên đăng nhập, Số điện thoại, Đơn vị, Mật khẩu.');
		}

		$headerIndexes = array_flip($headers);
		$departments = Department::query()->get()->keyBy('code');
		$accounts = [];
		$usernames = [];
		$errors = [];

		foreach (array_slice($rows, 1) as $index => $row) {
			$line = $index + 2;
			$account = collect($headerIndexes)
				->map(fn (int $columnIndex) => trim($row[$columnIndex] ?? ''))
				->all();

			if (implode('', $account) === '') {
				continue;
			}

			$validator = Validator::make($account, [
				'tên đăng nhập' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/D', 'unique:users,username'],
				'số điện thoại' => ['required', 'string', 'max:20'],
				'đơn vị' => ['required', 'string', 'max:255'],
				'mật khẩu' => ['required', 'string', 'min:8', 'max:255', 'regex:/^\S+$/'],
			]);

			if ($validator->fails()) {
				foreach ($validator->errors()->all() as $message) {
					$errors[] = "Dòng {$line}: {$message}";
				}

				continue;
			}

			$username = $account['tên đăng nhập'];
			$departmentValue = $account['đơn vị'];
			$departmentCode = Str::before($departmentValue, ' - ');

			if (isset($usernames[$username])) {
				$errors[] = "Dòng {$line}: tên đăng nhập bị lặp trong tệp.";
				continue;
			}

			$department = $departments->get($departmentCode);

			if ($department === null) {
				$errors[] = "Dòng {$line}: mã đơn vị không tồn tại.";
				continue;
			}

			$usernames[$username] = true;
			$accounts[] = [
				'department' => $department,
				'username' => $username,
				'phone' => $account['số điện thoại'],
				'password' => $account['mật khẩu'],
				'is_active' => true,
			];
		}

		if ($errors !== []) {
			throw \Illuminate\Validation\ValidationException::withMessages(['accountSpreadsheet' => $errors]);
		}

		if ($accounts === []) {
			throw new \RuntimeException('Tệp Excel không có tài khoản hợp lệ để tạo.');
		}

		return $accounts;
	}

	private function spreadsheetColumnIndex(string $reference): int
	{
		preg_match('/[A-Z]+/', $reference, $matches);
		$column = $matches[0] ?? 'A';
		$index = 0;

		foreach (str_split($column) as $letter) {
			$index = ($index * 26) + (ord($letter) - 64);
		}

		return $index - 1;
	}

	private function generatedUsername(Department $department): string
	{
		$departmentNames = [];

		while ($department !== null) {
			array_unshift($departmentNames, $department->name);
			$department = $department->parent;
		}

		return collect($departmentNames)
			->map(fn (string $name) => Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString())
			->implode('.');
	}
};
?>

<div class="h-full bg-slate-100 p-4 sm:p-6" x-data="{
	dialog: null,
	init() {
		this.$watch('dialog', (value) => {
			if (value) {
				this.$nextTick(() => this.$refs.dialogPanel?.querySelector('input, button, select, textarea')?.focus());
			}
		});
	},
	trapFocus(event) {
		const focusable = [...this.$refs.dialogPanel.querySelectorAll(`button, input, select, textarea, [href], [tabindex]:not([tabindex=&quot;-1&quot;])`)]
			.filter((element) => !element.disabled && element.offsetParent !== null);

		if (focusable.length === 0) {
			return;
		}

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
}" x-on:keydown.escape.window="dialog = null">
	<div class="mx-auto flex max-w-7xl flex-col gap-5">
		<div>
			<h1 class="text-2xl font-bold text-slate-900">Người dùng</h1>
			<p class="mt-1 text-sm text-slate-500">Tạo và quản lý các tài khoản thuộc đơn vị bạn phụ trách.</p>
		</div>

		<div class="grid gap-0 xl:grid-cols-[22rem_minmax(0,1fr)]">
			<section class="border border-slate-200 bg-white">
				<div class="border-b border-slate-200 px-5 py-4">
					<h2 class="font-semibold text-slate-900">Đơn vị</h2>
					<p class="mt-1 text-sm text-slate-500">Chọn đơn vị để xem tài khoản.</p>
				</div>
				<div class="flex max-h-[calc(100vh-15rem)] flex-col gap-1 overflow-y-auto p-3">
					@foreach ($departmentRows as $row)
						@php($department = $row['department'])
						@php($hasChildren = $treeDepartments->contains('parent_id', $department->id))
						@php($isSelectable = $departments->contains('id', $department->id))
						<div wire:key="department-tree-{{ $department->id }}" class="flex items-stretch gap-1" style="padding-left: {{ $row['depth'] * 1.1 }}rem">
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
						<h2 class="font-semibold text-slate-900">Tài khoản được quản lý</h2>
						<p class="mt-1 text-sm text-slate-500">{{ $departments->firstWhere('id', $selectedDepartmentId)?->name }} · {{ $users->count() }} tài khoản</p>
					</div>
					<div class="flex flex-wrap gap-2">
						<button type="button" x-on:click="dialog = 'create'" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-800">Tạo tài khoản</button>
						<button type="button" x-on:click="dialog = 'bulk'" class="rounded-md border border-teal-700 px-4 py-2 text-sm font-semibold text-teal-700 transition hover:bg-teal-50">Nhập từ file Excel</button>
					</div>
				</div>
				<div class="overflow-x-auto">
					<table class="w-full min-w-3xl table-fixed divide-y divide-slate-200 text-sm">
						<thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
							<tr><th class="w-40 px-5 py-3">Tài khoản</th><th class="w-64 px-5 py-3">Đơn vị</th><th class="w-36 px-5 py-3">Điện thoại</th><th class="w-36 px-5 py-3">Trạng thái</th><th class="w-28 px-5 py-3"><span class="sr-only">Thao tác</span></th></tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							@forelse ($users as $user)
								<tr wire:key="user-{{ $user->id }}" class="text-slate-700">
									<td class="wrap-break-word whitespace-normal px-5 py-3 font-medium text-slate-900">{{ $user->username }}</td>
									<td class="wrap-break-word whitespace-normal px-5 py-3">{{ $user->department?->code }} - {{ $user->department?->name }}</td>
									<td class="wrap-break-word whitespace-normal px-5 py-3">{{ $user->phone }}</td>
									<td class="wrap-break-word whitespace-normal px-5 py-3"><span class="font-medium {{ $user->is_active ? 'text-teal-700' : 'text-slate-500' }}">{{ $user->is_active ? 'Đang hoạt động' : 'Đã tắt' }}</span></td>
									<td class="px-5 py-3 text-right"><button type="button" x-on:click="dialog = 'edit'; $wire.beginEditing({{ $user->id }}).then(() => dialog = 'edit')" class="font-semibold text-teal-700 hover:text-teal-900">Chỉnh sửa</button></td>
								</tr>
							@empty
								<tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Chưa có tài khoản nào trong các đơn vị được quản lý.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<div x-cloak x-show="dialog !== null || $wire.editingUserId !== null" x-transition.opacity class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/65 p-4" role="dialog" aria-modal="true">
			<div x-ref="dialogPanel" x-on:keydown.tab="trapFocus($event)" class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto border-2 border-slate-300 bg-white shadow-xl">
				<div class="flex items-start justify-between border-b-2 border-slate-200 bg-slate-50 px-5 py-4">
					<div>
						<h2 class="font-semibold text-slate-900" x-text="dialog === 'bulk' ? 'Tạo hàng loạt từ Excel' : (dialog === 'edit' || $wire.editingUserId !== null ? 'Chỉnh sửa tài khoản' : 'Tạo tài khoản')"></h2>
						<p class="mt-1 text-sm text-slate-500">{{ $departments->firstWhere('id', $selectedDepartmentId)?->name }}</p>
					</div>
					<button type="button" x-on:click="$wire.editingUserId !== null ? $wire.cancelEditing().then(() => dialog = null) : dialog = null" class="text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="Đóng">&times;</button>
				</div>

				<form x-show="dialog === 'create'" wire:submit="saveUser" class="flex flex-col gap-4 p-5">
					<p class="border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Bạn chỉ có thể tạo tài khoản trong đơn vị của mình và các đơn vị trực thuộc.</p>
					@error('departmentId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
					<label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Tên đăng nhập<input wire:model="username" type="text" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('username') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
					<label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Số điện thoại<input wire:model="phone" type="text" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('phone') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
					<label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Mật khẩu<input wire:model="password" type="password" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('password') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
					<button type="submit" class="rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white hover:bg-teal-800" wire:loading.attr="disabled">Tạo tài khoản</button>
					<section class="border-t border-slate-200 pt-4" aria-labelledby="create-log-title">
						<div class="flex items-center justify-between gap-3">
							<h3 id="create-log-title" class="font-semibold text-slate-900">Nhật ký tạo tài khoản</h3>
							<span class="text-xs text-slate-500">{{ count($createLogs) }} hoạt động</span>
						</div>
						<div class="mt-2 flex h-24 flex-col gap-1 overflow-y-auto pr-2 text-sm text-slate-600" role="log" aria-live="polite">
							@if ($createLogs === [])
								<p class="text-slate-500">Chưa có hoạt động trong phiên này.</p>
							@else
								@foreach ($createLogs as $log)
									<div wire:key="create-log-{{ $loop->index }}" class="flex gap-2 leading-5 {{ $loop->last ? 'font-semibold text-teal-800' : '' }}" @if ($loop->last) aria-current="true" @endif>
										<time class="shrink-0 text-xs text-slate-400">{{ $log['created_at'] }}</time>
										<span>{{ $log['message'] }}</span>
									</div>
								@endforeach
							@endif
						</div>
					</section>
				</form>

				<form x-show="dialog === 'bulk'" wire:submit="importAccounts" class="flex flex-col gap-4 p-5">
					<p class="text-sm leading-6 text-slate-600">Tệp .xlsx với các cột: Tên đăng nhập, Mật khẩu, Số điện thoại, Đơn vị. Cột Đơn vị chỉ nhận giá trị từ danh sách trong mẫu.</p>
					<button type="button" wire:click="downloadTemplate" class="w-fit rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-teal-600 hover:bg-teal-50 hover:text-teal-800">Tải mẫu Excel</button>
					<input wire:model="accountSpreadsheet" type="file" accept=".xlsx" class="block w-full rounded-md border border-dashed border-slate-400 bg-slate-50 px-3 py-3 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:font-semibold file:text-white hover:border-teal-500 focus:border-teal-600 focus:outline-none focus:ring-0">
					@error('accountSpreadsheet') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
					<button type="submit" class="rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white hover:bg-teal-800" wire:loading.attr="disabled">Nhập tài khoản</button>
				</form>

				<div x-show="dialog === 'bulk' && $wire.bulkImportSuccess !== ''" x-cloak class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-teal-50 p-6 text-center">
					<span class="flex h-14 w-14 items-center justify-center rounded-full bg-teal-100">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-8 w-8 text-teal-700"><path d="M20 6 9 17l-5-5"/></svg>
					</span>
					<h3 class="text-lg font-semibold text-teal-900">Nhập tài khoản thành công</h3>
					<p class="text-sm text-teal-800">{{ $bulkImportSuccess }}</p>
					<button type="button" wire:click="$set('bulkImportSuccess', '')" class="mt-2 rounded-md bg-teal-700 px-6 py-2.5 font-semibold text-white hover:bg-teal-800">OK</button>
				</div>

				<div x-show="dialog === 'bulk' && $wire.bulkImportErrors.length > 0" x-cloak class="absolute inset-0 z-10 flex flex-col gap-3 bg-red-50 p-6">
					<div class="flex flex-col items-center gap-3 text-center">
						<span class="flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-8 w-8 text-red-700"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
						</span>
						<h3 class="text-lg font-semibold text-red-900">Nhập tài khoản thất bại</h3>
						<p class="text-sm text-red-800">{{ count($bulkImportErrors) }} lỗi được tìm thấy trong tệp Excel.</p>
					</div>
					<div class="flex-1 overflow-y-auto rounded-md border border-red-200 bg-white p-3">
						<ul class="flex flex-col gap-1 text-left text-xs text-red-700">
							@foreach ($bulkImportErrors as $error)
								<li wire:key="bulk-import-error-{{ $loop->index }}">{{ $error }}</li>
							@endforeach
						</ul>
					</div>
					<button type="button" wire:click="$set('bulkImportErrors', [])" class="mx-auto rounded-md bg-red-700 px-6 py-2.5 font-semibold text-white hover:bg-red-800">OK</button>
				</div>

				<div x-show="dialog === 'edit' || $wire.editingUserId !== null" class="grid gap-6 p-5 lg:grid-cols-2">
					<form wire:submit="updateUser" class="flex flex-col gap-4">
						<label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Số điện thoại<input wire:model="editingPhone" type="text" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('editingPhone') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
						<label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input wire:model="editingIsActive" type="checkbox" class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">Kích hoạt tài khoản</label>
						<div class="flex gap-3"><button type="submit" class="rounded-md bg-teal-700 px-4 py-2 font-semibold text-white hover:bg-teal-800">Lưu thay đổi</button><button type="button" wire:click="cancelEditing" x-on:click="dialog = null" class="rounded-md border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:bg-slate-50">Hủy</button></div>
					</form>
					<form wire:submit="resetUserPassword" class="flex flex-col gap-4 border-t border-slate-200 pt-6 lg:border-t-0 lg:border-l lg:pl-6 lg:pt-0">
						<div><h3 class="font-semibold text-slate-900">Đặt lại mật khẩu</h3><p class="mt-1 text-sm text-slate-500">Mật khẩu mới phải có ít nhất 8 ký tự.</p></div>
						<label class="flex flex-col gap-1 text-sm font-medium text-slate-700">Mật khẩu mới<input wire:model="newPassword" type="password" class="rounded-md border border-slate-400 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-teal-600 focus:ring-0">@error('newPassword') <span class="text-xs font-normal text-red-600">{{ $message }}</span> @enderror</label>
						<button type="submit" class="w-fit rounded-md border border-teal-700 px-4 py-2 font-semibold text-teal-700 hover:bg-teal-50">Đặt lại mật khẩu</button>
					</form>
					<section x-data x-init="const log = $el.querySelector('[role=log]'); const scrollToNewest = () => log?.scrollTo({ top: log.scrollHeight, behavior: 'smooth' }); scrollToNewest(); new MutationObserver(scrollToNewest).observe(log, { childList: true });" class="border-t border-slate-200 pt-5 lg:col-span-2" aria-labelledby="edit-log-title">
						<div class="flex items-center justify-between gap-3">
							<h3 id="edit-log-title" class="font-semibold text-slate-900">Nhật ký chỉnh sửa</h3>
							<span class="text-xs text-slate-500">{{ count($editLogs) }} hoạt động</span>
						</div>
						<div class="mt-3 flex h-32 flex-col gap-1 overflow-y-auto pr-2 text-sm text-slate-600" role="log" aria-live="polite">
							@if ($editLogs === [])
								<p class="text-slate-500">Chưa có hoạt động trong phiên này.</p>
							@else
								@foreach ($editLogs as $log)
									<div wire:key="edit-log-{{ $loop->index }}" class="flex gap-2 leading-5 {{ $loop->last ? 'font-semibold text-teal-800' : '' }}" @if ($loop->last) aria-current="true" @endif>
										<time class="shrink-0 text-xs text-slate-400">{{ $log['created_at'] }}</time>
										<span>{{ $log['message'] }}</span>
										{{-- @if ($loop->last)
											<span class="shrink-0 text-xs font-medium text-teal-700">Mới nhất</span>
										@endif --}}
									</div>
								@endforeach
							@endif
						</div>
					</section>
				</div>
			</div>
		</div>
	</div>
</div>
