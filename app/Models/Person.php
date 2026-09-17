<?php

namespace App\Models;

use App\Models\Concerns\ValidatesPermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'full_name', 'identity_number', 'phone', 'person_type_id', 'department_id', 'is_active', 'note', 'special_permission', 'permission'])]
class Person extends Model
{
    use ValidatesPermission;

    protected $table = 'persons';

    protected function casts(): array
    {
        return [
            'permission' => 'array',
            'special_permission' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
