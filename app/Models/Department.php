<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'description', 'parent_id', 'sort_order', 'is_active'])]
class Department extends Model
{
    // auto fill code (if null) when creating new department
    protected static function booted()
    {
        static::creating(function ($department) {
            if (($department->code == '' || $department->code == null)) {
                $department->code = static::nextCode($department->parent_id);
            } elseif (! preg_match('/^[A-Za-z0-9._-]+$/', $department->code)) {
                throw ValidationException::withMessages([
                    'code' => 'Mã đơn vị chỉ được chứa chữ cái, số, dấu chấm, dấu gạch ngang và dấu gạch dưới.',
                ]);
            }
        });
    }

    protected static function nextCode(?int $parentId): string
    {
        $parent = $parentId ? static::findOrFail($parentId) : null;

        $prefix = $parent ? $parent->code.'.' : '';

        $number = static::where('parent_id', $parentId)->count() + 1;

        if ($number > 999) {
            throw ValidationException::withMessages([
                'parent_code' => 'Đơn vị này đã có đủ 999 đơn vị con. Mỗi đơn vị chỉ được có tối đa 999 đơn vị trực thuộc',
            ]);
        }

        return $prefix.str_pad($number, 3, '0', STR_PAD_LEFT);
    }
    //
}
