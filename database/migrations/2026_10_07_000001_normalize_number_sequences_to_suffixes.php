<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert the existing full-number counters to their last three digits.
     *
     * Historical SF/PF records are deliberately left untouched. They keep the
     * number they were originally issued. Only the live counter becomes a
     * suffix-only counter so that the next generated number can use the new
     * YYMMXXX format.
     */
    public function up(): void
    {
        $prefix = now()->format('ym');
        $sequences = DB::table('number_sequences')
            ->whereIn('key', ['SF', 'PF'])
            ->get(['key', 'next_number']);

        $updates = [];

        foreach ($sequences as $sequence) {
            $suffix = ((int) $sequence->next_number) % 1000;
            $newNumber = (int) ($prefix . str_pad((string) $suffix, 3, '0', STR_PAD_LEFT));
            $table = $sequence->key === 'SF' ? 'sf_records' : 'pf_records';
            $column = $sequence->key === 'SF' ? 'sf_number' : 'pf_number';

            if (DB::table($table)->where($column, $newNumber)->exists()) {
                throw new RuntimeException(
                    "Cannot migrate {$sequence->key} sequence: generated number {$newNumber} already exists. Resolve the counter before enabling the new YYMMXXX format."
                );
            }

            $updates[$sequence->key] = $suffix;
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as $key => $suffix) {
                DB::table('number_sequences')
                    ->where('key', $key)
                    ->update(['next_number' => $suffix]);
            }
        });
    }

    /**
     * The old YYMM portion cannot be reconstructed from a suffix-only counter.
     * Historical records already retain their original values, so there is no
     * safe automatic reverse transformation.
     */
    public function down(): void
    {
        // Intentionally irreversible.
    }
};
