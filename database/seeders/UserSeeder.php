<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Initial accounts. Usernames and roles are not secret; passwords are never
     * written in source code (see config/sfpf.php -> seed_password).
     */
    public const USERS = [
        ['username' => 'Alida', 'role' => User::ROLE_USER],
        ['username' => 'Windy', 'role' => User::ROLE_USER],
        ['username' => 'Aqiqah', 'role' => User::ROLE_USER],
        ['username' => 'Tohal', 'role' => User::ROLE_USER],
        ['username' => 'Adam', 'role' => User::ROLE_USER],
        ['username' => 'Finance', 'role' => User::ROLE_ADMIN],
    ];

    public function run(): void
    {
        // Development convenience: one shared password from .env (SEED_USER_PASSWORD).
        // When empty, every NEW user gets a random password that is printed once below.
        $shared = config('sfpf.seed_password');
        $rows = [];

        foreach (self::USERS as $account) {
            // Re-running the seeder never touches (or re-issues a password for) an existing user.
            if (User::where('username', $account['username'])->exists()) {
                $rows[] = [$account['username'], $account['role'], '(already exists - unchanged)'];

                continue;
            }

            $plain = filled($shared) ? $shared : Str::password(16, symbols: false);

            User::create([
                'username' => $account['username'],
                'role' => $account['role'],
                'password' => $plain, // hashed with Argon2id by the model's "hashed" cast
            ]);

            $rows[] = [$account['username'], $account['role'], filled($shared) ? '(SEED_USER_PASSWORD)' : $plain];
        }

        $this->command?->table(['Username', 'Role', 'Initial password'], $rows);

        if (! filled($shared)) {
            $this->command?->warn('Random passwords are shown ONCE and are stored only as Argon2id hashes. Note them now,');
            $this->command?->warn('or reset them later with: php artisan user:password <username>');
        }
    }
}
