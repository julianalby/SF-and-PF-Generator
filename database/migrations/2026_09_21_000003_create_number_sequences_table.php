<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();          // "SF" or "PF"
            $table->unsignedBigInteger('next_number');    // the number the NEXT record will receive
            $table->timestamps();
        });

        // One independent counter per form type, initialised from config/sfpf.php
        // (SF = 26090119, PF = 26090138 by default). Inserted here so a plain
        // `php artisan migrate` is enough for numbering to work.
        $now = now();

        foreach (config('sfpf.sequences') as $key => $start) {
            DB::table('number_sequences')->insert([
                'key' => $key,
                'next_number' => $start,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
