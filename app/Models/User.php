<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Fillable(['username', 'password', 'phone', 'status', 'person_id', 'department_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->exists && $user->isDirty('username')) {
                throw ValidationException::withMessages([
                    'username' => 'Không được thay đổi tên đăng nhập sau khi tạo tài khoản.',
                ]);
            }

            if (is_string($user->username) && preg_match('/^[A-Za-z0-9._-]+$/D', $user->username) !== 1) {
                throw ValidationException::withMessages([
                    'username' => 'Tên đăng nhập chỉ được chứa chữ cái không dấu, số, dấu chấm, dấu gạch ngang và dấu gạch dưới.',
                ]);
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }
}
