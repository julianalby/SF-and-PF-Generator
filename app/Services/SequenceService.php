<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/**
 * Atomic, concurrency-safe SF / PF number generation.
 *
 * Each sequence is one row in `number_sequences`; `next_number` is the number
 * the next record will receive. Numbers are NEVER derived from
 * MAX(sf_number) + 1 (which two simultaneous requests would both read).
 */
class SequenceService
{
    /**
     * Reserve and return the next number of a sequence ("SF" or "PF").
     *
     * MUST be called inside a database transaction, together with the INSERT
     * of the record that will carry the number. If that transaction rolls back,
     * the counter increment rolls back with it, so a number is only consumed by
     * a record that was actually saved.
     *
     * Why this is safe under concurrency:
     *  - The FIRST statement is an UPDATE (a write). The writer takes the
     *    database write lock immediately (SQLite) / the row lock (MySQL, Postgres)
     *    and holds it until COMMIT, so a second request waits (bounded by
     *    busy_timeout / lock wait) instead of reading the same value.
     *  - Starting the transaction with a read and upgrading to a write later is
     *    the classic SQLite deadlock/"database is locked" trap; we never do that.
     *  - The follow-up SELECT runs in the same transaction and therefore sees
     *    our own increment, not another request's.
     *  - UNIQUE constraints on sf_number / pf_number are the last line of defence.
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

        $nextUnused = (int) NumberSequence::query()->where('key', $key)->value('next_number');

        return $nextUnused - 1; // the value the counter held before our increment
    }
}
