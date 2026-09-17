<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait ValidatesPermission
{
    protected static function bootValidatesPermission(): void
    {
        forward_static_call([static::class, 'saving'], static function (Model $model): void {
            $model->validatePermission();
        });
    }

    protected function validatePermission(): void
    {
        $permission = $this->getAttribute('permission');

        if ($permission === null || $permission === []) {
            return;
        }

        if (! is_array($permission) || array_diff(array_keys($permission), $this->permissionKeys()) !== []) {
            $this->throwPermissionValidationException('permission chứa khóa điều kiện không được hỗ trợ.');
        }

        foreach (['allowed_date', 'excluded_date'] as $key) {
            $this->validatePermissionRanges($permission[$key] ?? [], $key, 'd/m/Y');
        }

        foreach (['allowed_time', 'excluded_time'] as $key) {
            $this->validatePermissionRanges($permission[$key] ?? [], $key, 'H:i');
        }

        if (isset($permission['allowed_weekdays'])
            && (! is_array($permission['allowed_weekdays'])
                || ! array_is_list($permission['allowed_weekdays'])
                || array_filter($permission['allowed_weekdays'], static fn (mixed $day): bool => ! is_int($day) || $day < 1 || $day > 7) !== [])) {
            $this->throwPermissionValidationException('allowed_weekdays phải là danh sách các số từ 1 đến 7.');
        }

        foreach (['max_entries_per_day', 'max_entries', 'max_duration_per_session', 'max_duration'] as $key) {
            if (isset($permission[$key]) && (! is_int($permission[$key]) || $permission[$key] < 1)) {
                $this->throwPermissionValidationException("{$key} phải là số nguyên dương.");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function permissionKeys(): array
    {
        return [
            'allowed_date',
            'excluded_date',
            'allowed_time',
            'excluded_time',
            'allowed_weekdays',
            'max_entries_per_day',
            'max_entries',
            'max_duration_per_session',
            'max_duration',
        ];
    }

    private function validatePermissionRanges(mixed $ranges, string $key, string $format): void
    {
        if (! is_array($ranges) || ! array_is_list($ranges)) {
            $this->throwPermissionValidationException("{$key} phải là danh sách các khoảng.");
        }

        foreach ($ranges as $range) {
            if (! is_array($range)
                || ! array_is_list(array_keys($range))
                || array_diff(array_keys($range), ['start', 'end']) !== []
                || ! isset($range['start'], $range['end'])
                || ! is_string($range['start'])
                || ! is_string($range['end'])) {
                $this->throwPermissionValidationException("{$key} phải gồm start và end.");
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
                    throw new \Exception;
                }
            } catch (\Throwable) {
                $this->throwPermissionValidationException("Khoảng {$key} không hợp lệ.");
            }
        }
    }

    private function throwPermissionValidationException(string $message): never
    {
        throw ValidationException::withMessages(['permission' => $message]);
    }
}
