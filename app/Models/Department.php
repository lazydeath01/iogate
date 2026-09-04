<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'description', 'parent_id', 'sort_order', 'is_active', 'code'])]
class Department extends Model
{
    // auto fill code (if null) when creating new department
    protected static function booted()
    {
        static::creating(function ($department) {
            $department->code = static::nextCode($department->parent_id);
        });
    }

    protected static function nextCode(?int $parentId): string
    {
        $parent = $parentId ? static::findOrFail($parentId) : null;

        $prefix = $parent ? $parent->code . '.' : '';

        $last = static::where('parent_id', $parentId)
            ->orderByRaw('CAST(SUBSTRING_INDEX(code, ".", -1) AS UNSIGNED) DESC')
            ->value('code');

        $number = $last
            ? (int) str_replace($prefix, '', $last) + 1
            : 1;

        if ($number > 999) {
            throw ValidationException::withMessages([
                'parent_code' => 'Đơn vị này đã có đủ 999 đơn vị con. Mỗi đơn vị chỉ được có tối đa 999 đơn vị trực thuộc',
            ]);
        }

        return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
    //
}
