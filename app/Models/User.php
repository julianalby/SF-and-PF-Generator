<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Validation\Rule;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public const ROLE_USER = 'User';

    public const ROLE_ADMIN = 'Admin';

    public const ROLES = [self::ROLE_USER, self::ROLE_ADMIN];

    protected $fillable = [
        'username',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            // Assigning a plaintext value hashes it with the configured driver
            // (Argon2id, see config/hashing.php) before it reaches the database.
            'password' => 'hashed',
        ];
    }

    /**
     * Normalise the role on write ("admin" -> "Admin") so users created by
     * hand or through the UI end up with the same canonical value.
     */
    protected function role(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) ? ucfirst(strtolower(trim($value))) : $value,
        );
    }

    /**
     * Case-insensitive on purpose: a row inserted by hand with role "admin"
     * must not silently lose (or gain) access. Anything that is not exactly
     * "admin" (ignoring case) is a normal user, so authorisation fails closed.
     */
    public function isAdmin(): bool
    {
        return strcasecmp(trim((string) $this->role), self::ROLE_ADMIN) === 0;
    }

    /** Shared by the admin form, the artisan commands and the tests. */
    public static function usernameRules(): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:50',
            'regex:/^[A-Za-z0-9._-]+$/',
            Rule::unique('users', 'username'), // case-insensitive on SQLite (NOCASE column)
        ];
    }

    public static function passwordRules(): array
    {
        return ['required', 'string', 'min:8', 'max:255'];
    }
}
