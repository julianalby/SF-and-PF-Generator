<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the month each counter is currently counting in.
     *
     * Existing counters are stamped with the CURRENT month, so run this in the
     * month the counters belong to (before the first record of a new month),
     * and the current month keeps counting where it is. The next month starts at 001.
     */
    public function up(): void
    {
        Schema::table('number_sequences', function (Blueprint $table) {
            $table->string('month_prefix', 4)->nullable()->after('next_number');
        });

        DB::table('number_sequences')->update(['month_prefix' => now()->format('ym')]);
    }

    public function down(): void
    {
        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropColumn('month_prefix');
        });
    }
};
