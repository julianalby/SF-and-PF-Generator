<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sf_records', function (Blueprint $table) {
            $table->id(); // also the "No" column of the SF database page
            $table->timestamps();

            $table->string('user', 50)->index();                // username of the creator (set server-side)
            $table->unsignedBigInteger('sf_number')->unique();  // from the SF sequence (set server-side)
            $table->string('vnid', 100);
            $table->string('customer_name');
            $table->string('service');

            // Idempotency key generated when the form is rendered: the same form
            // submitted twice (double click, refresh, back button) creates ONE record.
            $table->uuid('submission_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sf_records');
    }
};
