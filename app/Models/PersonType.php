<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'permission_id', 'permission'])]
class PersonType extends Model
{
    protected $table = 'person_types';

    protected function casts(): array
    {
        return [
            'permission' => 'array',
        ];
    }
}
