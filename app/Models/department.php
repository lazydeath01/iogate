<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Str;

#[Fillable(['name', 'description', 'parent_id', 'sort_order', 'is_active', 'code'])]
class department extends Model
{
    // auto fill code (if null) when creating new department
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->code) && !empty($model->name)) {
                $baseCode = Str::slug($model->name, '');
                $code = $baseCode;
                $stt = 2;

                while (static::where('code', $code)->exists()) {
                    $code = $baseCode . '_' . $stt;
                    $stt++;
                }
                $model->code = $code;
            }
        });
    }
    //
}
