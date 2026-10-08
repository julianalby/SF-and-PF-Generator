<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/**
 * Atomic, concurrency-safe SF / PF number generation.
 *
 * number = YYMM (creation month, APP_TIMEZONE) + 3-digit suffix 001-999.
 * The suffix restarts at 001 every month.
 *
 * Each sequence is one row in `number_sequences`:
 *   - month_prefix : the YYMM the counter is currently counting in
 *   - next_number  : the suffix the NEXT record of that month will receive
 *
 * Numbers are NEVER derived from MAX(sf_number) + 1.
 */
class SequenceService
{
    /**
     * Reserve and return the next generated number of a sequence ("SF" or "PF").
     *
     * MUST be called inside a database transaction, together with the INSERT
     * of the record that will carry the number. If that transaction rolls back,
     * the counter change (including a month reset) rolls back with it.
     *
     * Concurrency: the FIRST statement is a single UPDATE that does the month
     * check, the reset and the increment in one go. It takes the write lock
     * (SQLite) / row lock (MySQL, Postgres) immediately and holds it until
     * COMMIT, so two requests can never read the same value, not even in the
     * first seconds of a new month. UNIQUE constraints on sf_number / pf_number
     * remain the last line of defence.
     */
    public function next(string $key): int
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('A number sequence must be advanced inside a database transaction.');
        }

        // Digits only (e.g. "2611"), so it is safe to embed in the SQL below.
        $prefix = now()->format('ym');

        // Same month  -> next_number + 1
        // New month   -> next_number = 2, i.e. this record takes suffix 1.
        $updated = NumberSequence::query()
            ->where('key', $key)
            ->update([
                'next_number' => DB::raw("CASE WHEN month_prefix = '{$prefix}' THEN next_number + 1 ELSE 2 END"),
                'month_prefix' => $prefix,
            ]);

        if ($updated !== 1) {
            // Never fall back to "max + 1" or to 1: fail loudly instead of issuing wrong numbers.
            throw new RuntimeException("Number sequence [{$key}] is missing from the number_sequences table.");
        }

        $suffix = (int) NumberSequence::query()->where('key', $key)->value('next_number') - 1;

        if ($suffix < 1 || $suffix > 999) {
            throw new RuntimeException("The SF/PF numbering suffix for {$prefix} is outside its range (001-999).");
        }

        return (int) ($prefix . str_pad((string) $suffix, 3, '0', STR_PAD_LEFT));
    }
}
