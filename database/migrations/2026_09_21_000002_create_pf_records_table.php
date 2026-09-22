<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pf_records', function (Blueprint $table) {
            $table->id(); // also the "No" column of the PF database page
            $table->timestamps();

            $table->string('user', 50)->index();                // username of the creator (set server-side)
            $table->unsignedBigInteger('pf_number')->unique();  // from the PF sequence (set server-side)
            $table->string('project_name');

            // Optional references typed by the user (free text, may be empty).
            $table->string('sf_number', 100)->nullable();
            $table->string('vnid', 100)->nullable();
            $table->string('customer_name')->nullable();

            // See sf_records.submission_token.
            $table->uuid('submission_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pf_records');
    }
};
