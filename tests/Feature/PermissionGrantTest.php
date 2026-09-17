<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Person;
use App\Models\PersonType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PermissionGrantTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_user_can_grant_unrestricted_permission_to_department(): void
    {
        $user = $this->createUser(true);
        $department = Department::create(['name' => 'Phòng hành chính']);

        $this->actingAs($user);

        Livewire::test('permissions-content')
            ->set('targetId', $department->id)
            ->set('permissionMode', 'unrestricted')
            ->call('savePermission')
            ->assertSee('Đã lưu quyền thành công')
            ->call('closeSuccessDialog')
            ->assertDontSee('Đã lưu quyền thành công');

        $this->assertSame([], $department->refresh()->permission);
    }

    public function test_non_system_user_cannot_open_permission_editor(): void
    {
        $this->actingAs($this->createUser(false));

        $this->get('/permissions')->assertForbidden();
    }

    public function test_system_user_loads_all_department_targets_on_mount(): void
    {
        $this->actingAs($this->createUser(true));
        Department::create(['name' => 'Phòng hành chính']);
        Department::create(['name' => 'Phòng kỹ thuật']);

        Livewire::test('permissions-content')
            ->assertSee('Phòng kỹ thuật')
            ->assertSee('Phòng hành chính');
    }

    public function test_validation_failure_shows_blocking_error_dialog_until_dismissed(): void
    {
        $this->actingAs($this->createUser(true));

        Livewire::test('permissions-content')
            ->set('permissionMode', 'unrestricted')
            ->call('savePermission')
            ->assertHasErrors(['targetId'])
            ->assertSee('Có lỗi cần sửa')
            ->assertSee('Đối tượng được chọn')
            ->call('closeErrorDialog')
            ->assertDontSee('Có lỗi cần sửa')
            ->assertHasErrors(['targetId'])
            ->assertSee('Hãy chọn đối tượng cần cấp quyền.')
            ->assertSee('ring-inset ring-red-400');
    }

    public function test_invalid_allowed_date_is_reported_in_allowed_date_section(): void
    {
        $this->actingAs($this->createUser(true));
        $department = Department::create(['name' => 'Phòng hành chính']);

        Livewire::test('permissions-content')
            ->set('targetId', $department->id)
            ->set('permissionMode', 'constrained')
            ->set('allowedDates', [['start' => '31/02/2026', 'end' => '01/03/2026']])
            ->call('savePermission')
            ->assertHasErrors(['allowedDates'])
            ->assertSee('Ngày được phép')
            ->call('closeErrorDialog')
            ->assertSee('ring-2 ring-red-400 ring-offset-2')
            ->call('removeRange', 'allowed_date', 0)
            ->assertHasNoErrors()
            ->assertDontSee('Khoảng allowed_date không hợp lệ.')
            ->assertSet('highlightedErrorAreas', []);
    }

    public function test_switching_target_clears_previous_validation_state(): void
    {
        $this->actingAs($this->createUser(true));
        $firstDepartment = Department::create(['name' => 'Phòng hành chính']);
        $secondDepartment = Department::create(['name' => 'Phòng kỹ thuật']);

        Livewire::test('permissions-content')
            ->set('targetId', $firstDepartment->id)
            ->set('permissionMode', 'constrained')
            ->set('allowedDates', [['start' => '31/12/2026', 'end' => '01/01/2026']])
            ->call('savePermission')
            ->call('closeErrorDialog')
            ->assertSee('Khoảng allowed_date không hợp lệ.')
            ->call('selectTarget', $secondDepartment->id)
            ->assertHasNoErrors()
            ->assertDontSee('Khoảng allowed_date không hợp lệ.')
            ->assertDontSee('ring-2 ring-red-400 ring-offset-2');
    }

    public function test_system_user_can_grant_constrained_permission_to_role_and_person(): void
    {
        $this->actingAs($this->createUser(true));
        $department = Department::create(['name' => 'Phòng kỹ thuật']);
        $personType = PersonType::create(['name' => 'Nhân viên']);
        $person = Person::create([
            'code' => 'P001',
            'full_name' => 'Nguyễn Văn A',
            'phone' => '0123456789',
            'person_type_id' => $personType->id,
            'department_id' => $department->id,
        ]);
        $constraints = [
            'allowed_weekdays' => [2, 3, 4, 5, 6],
            'allowed_time' => [['start' => '08:00', 'end' => '17:00']],
            'max_entries_per_day' => 2,
        ];

        Livewire::test('permissions-content')
            ->set('targetType', 'role')
            ->set('targetId', $personType->id)
            ->set('permissionMode', 'constrained')
            ->set('allowedWeekdays', ['2', '3', '4', '5', '6'])
            ->set('allowedTimes', $constraints['allowed_time'])
            ->set('maxEntriesPerDay', 2)
            ->call('savePermission');

        Livewire::test('permissions-content')
            ->set('targetType', 'person')
            ->set('targetId', $person->id)
            ->set('permissionMode', 'constrained')
            ->set('allowedWeekdays', ['2', '3', '4', '5', '6'])
            ->set('allowedTimes', $constraints['allowed_time'])
            ->set('maxEntriesPerDay', 2)
            ->call('savePermission');

        $this->assertEquals($constraints, $personType->refresh()->permission);
        $this->assertEquals($constraints, $person->refresh()->permission);
    }

    public function test_all_permission_targets_reject_unsupported_permission_keys(): void
    {
        $department = Department::create(['name' => 'Phòng hành chính']);
        $personType = PersonType::create(['name' => 'Nhân viên']);
        $person = Person::create([
            'code' => 'P001',
            'full_name' => 'Nguyễn Văn A',
            'phone' => '0123456789',
            'person_type_id' => $personType->id,
            'department_id' => $department->id,
        ]);

        foreach ([$department, $personType, $person] as $target) {
            $target->permission = ['unsupported' => true];

            $this->assertThrows(
                fn () => $target->save(),
                ValidationException::class,
            );
        }
    }

    public function test_permission_validation_rejects_invalid_ranges_weekdays_and_limits(): void
    {
        $invalidPermissions = [
            ['allowed_date' => [['start' => '31/02/2026', 'end' => '01/03/2026']]],
            ['allowed_time' => [['start' => '18:00', 'end' => '08:00']]],
            ['allowed_weekdays' => [0, 8]],
            ['allowed_weekdays' => ['1']],
            ['max_entries' => 0],
        ];

        foreach ($invalidPermissions as $permission) {
            $this->assertThrows(
                fn () => Department::create([
                    'name' => 'Phòng hành chính',
                    'permission' => $permission,
                ]),
                ValidationException::class,
            );
        }
    }

    private function createUser(bool $isSystem): User
    {
        return User::forceCreate([
            'username' => $isSystem ? 'system-user' : 'regular-user',
            'phone' => '0123456789',
            'password' => 'password',
            'is_active' => true,
            'is_system' => $isSystem,
        ]);
    }
}
