<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class SetUserPassword extends Command
{
    protected $signature = 'user:password
                            {username : Existing login name}
                            {--password= : Plain password; prompted (hidden) when omitted}';

    protected $description = 'Set a new password for a user (stored as an Argon2id hash)';

    public function handle(): int
    {
        $user = User::where('username', $this->argument('username'))->first();

        if ($user === null) {
            $this->error('No user with that username.');

            return self::FAILURE;
        }

        $password = $this->option('password');
        $confirmation = $password;

        if (blank($password)) {
            $password = $this->secret('New password (input hidden)');
            $confirmation = $this->secret('Confirm new password');
        }

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => [...User::passwordRules(), 'confirmed']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user->forceFill(['password' => $password])->save(); // hashed by the model cast

        $this->info("Password for [{$user->username}] was updated.");

        return self::SUCCESS;
    }
}
