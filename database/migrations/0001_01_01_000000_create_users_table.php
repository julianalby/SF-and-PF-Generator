<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Username (not e-mail) is the login. On SQLite the column uses the
            // NOCASE collation, so "Windy", "windy" and "WINDY" are the same account
            // for the UNIQUE index as well as for login look-ups.
            $username = $table->string('username', 50);
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $username->collation('nocase');
            }
            $username->unique();

            $table->string('password'); // Argon2id hash, never plaintext
            $table->string('role', 20)->default('User'); // "User" or "Admin"
            $table->rememberToken();
            $table->timestamps();
        });

        // Only used when SESSION_DRIVER=database (the default .env uses "file").
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
