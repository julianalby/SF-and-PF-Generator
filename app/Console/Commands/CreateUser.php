<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateUser extends Command
{
    protected $signature = 'user:create
                            {username : Login name (letters, numbers, . _ -)}
                            {--role=User : User or Admin}
                            {--password= : Plain password; prompted (hidden) when omitted}';

    protected $description = 'Create a user; the password is stored as an Argon2id hash';

    public function handle(): int
    {
        $password = $this->option('password');
        $confirmation = $password;

        if (blank($password)) {
            $password = $this->secret('Password (input hidden)');
            $confirmation = $this->secret('Confirm password');
        }

        $role = ucfirst(strtolower((string) $this->option('role')));

        $validator = Validator::make([
            'username' => $this->argument('username'),
            'role' => $role,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'username' => User::usernameRules(),
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => [...User::passwordRules(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'username' => $this->argument('username'),
            'role' => $role,
            'password' => $password,
        ]);

        $this->info("User [{$user->username}] created with role [{$user->role}].");

        return self::SUCCESS;
    }
}
