<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/**
 * Atomic, concurrency-safe SF / PF number generation.
 *
 * Each sequence is one row in `number_sequences`; `next_number` is now the
 * next three-digit suffix the next record will receive. The YYMM prefix is
 * generated from the creation time and is never stored in the counter.
 * Numbers are NEVER derived from MAX(sf_number) + 1.
 */
class SequenceService
{
    /**
     * Reserve and return the next generated number of a sequence ("SF" or "PF").
     *
     * MUST be called inside a database transaction, together with the INSERT
     * of the record that will carry the number. If that transaction rolls back,
     * the counter increment rolls back with it, so a suffix is only consumed by
     * a record that was actually saved.
     *
     * The suffix is intentionally continuous across months. Only the first four
     * digits change with the creation year/month (YYMM).
     *
     * Why this is safe under concurrency:
     *  - The FIRST statement is an UPDATE (a write). The writer takes the
     *    database write lock immediately (SQLite) / the row lock (MySQL, Postgres)
     *    and holds it until COMMIT, so a second request waits instead of reading
     *    the same value.
     *  - Starting the transaction with a read and upgrading to a write later is
     *    the classic SQLite deadlock/"database is locked" trap; we never do that.
     *  - The follow-up SELECT runs in the same transaction and therefore sees
     *    our own increment, not another request's.
     *  - UNIQUE constraints on sf_number / pf_number remain the last line of defence.
     */
    public function next(string $key): int
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('A number sequence must be advanced inside a database transaction.');
        }

        $updated = NumberSequence::query()
            ->where('key', $key)
            ->increment('next_number');

        if ($updated !== 1) {
            // Never fall back to "max + 1" or to 1: fail loudly instead of issuing wrong numbers.
            throw new RuntimeException("Number sequence [{$key}] is missing from the number_sequences table.");
        }

        $nextSuffix = (int) NumberSequence::query()->where('key', $key)->value('next_number');
        $suffix = $nextSuffix - 1; // the value the counter held before our increment

        if ($suffix < 0 || $suffix > 999) {
            throw new RuntimeException('The SF/PF numbering suffix has exhausted its three-digit range (000-999).');
        }

        $prefix = now()->format('ym');

        return (int) ($prefix . str_pad((string) $suffix, 3, '0', STR_PAD_LEFT));
    }
}
