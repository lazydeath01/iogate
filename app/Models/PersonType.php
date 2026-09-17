<?php

namespace App\Models;

use App\Models\Concerns\ValidatesPermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'permission_id', 'permission'])]
class PersonType extends Model
{
    use ValidatesPermission;

    protected $table = 'person_types';

    protected function casts(): array
    {
        return [
            'permission' => 'array',
        ];
    }
}
