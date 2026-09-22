<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The SF / PF sequences are created by their migration, not here.
        $this->call(UserSeeder::class);
    }
}
