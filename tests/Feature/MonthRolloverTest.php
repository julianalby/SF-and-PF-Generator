<?php

namespace Tests\Feature;

use App\Models\NumberSequence;
use App\Models\PfRecord;
use App\Models\SfRecord;
use App\Services\SequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Drop into tests/Feature/. Freezes the clock so month changes can be tested
 * any day of the year.
 *
 * Rule: number = YYMM (Asia/Jakarta) + 3-digit suffix that restarts at 001
 * every month.
 *
 * Dates are built in Asia/Jakarta on purpose: now() keeps the timezone of the
 * frozen instance, so a UTC instance would test the wrong thing.
 */
class MonthRolloverTest extends TestCase
{
    private function at(string $datetime): Carbon
    {
        return Carbon::parse($datetime, 'Asia/Jakarta');
    }

    /** Put a counter into a known state: counting in $prefix, next record gets suffix $nextSuffix. */
    private function setCounter(string $key, string $prefix, int $nextSuffix): void
    {
        // next_number = (last issued suffix) + 1, which is also the suffix the next record gets.
        NumberSequence::where('key', $key)->update([
            'month_prefix' => $prefix,
            'next_number' => $nextSuffix,
        ]);
    }

    private function nextSf(): int
    {
        return DB::transaction(fn () => app(SequenceService::class)->next('SF'));
    }

    private function nextPf(): int
    {
        return DB::transaction(fn () => app(SequenceService::class)->next('PF'));
    }

    public function test_within_a_month_numbers_are_consecutive(): void
    {
        $this->setCounter('SF', '2610', 120); // suffix 119 was the last one issued

        $this->travelTo($this->at('2026-10-10 09:00:00'));

        $this->assertSame([2610120, 2610121, 2610122], [$this->nextSf(), $this->nextSf(), $this->nextSf()]);
    }

    public function test_the_suffix_restarts_at_001_at_jakarta_midnight(): void
    {
        $this->setCounter('SF', '2610', 120);

        $this->travelTo($this->at('2026-10-31 23:59:59'));
        $last = $this->nextSf();

        $this->travelTo($this->at('2026-11-01 00:00:00'));
        $first = $this->nextSf();
        $second = $this->nextSf();

        $this->assertSame(2610120, $last);
        $this->assertSame(2611001, $first);
        $this->assertSame(2611002, $second);
    }

    public function test_year_end_rolls_to_the_next_year_and_restarts(): void
    {
        $this->setCounter('SF', '2612', 6);

        $this->travelTo($this->at('2026-12-31 23:59:59'));
        $dec = $this->nextSf();

        $this->travelTo($this->at('2027-01-01 00:00:00'));
        $jan = $this->nextSf();

        $this->assertSame(2612006, $dec);
        $this->assertSame(2701001, $jan);
    }

    public function test_a_month_with_no_records_does_not_matter(): void
    {
        $this->setCounter('SF', '2610', 120);

        $this->travelTo($this->at('2027-02-15 10:00:00')); // Nov-Jan had no records at all

        $this->assertSame(2702001, $this->nextSf());
    }

    public function test_sf_and_pf_reset_independently(): void
    {
        $this->setCounter('SF', '2610', 50);
        $this->setCounter('PF', '2610', 7);

        $this->travelTo($this->at('2026-11-01 09:00:00'));

        $this->assertSame(2611001, $this->nextSf());
        $this->assertSame(2611002, $this->nextSf());
        $this->assertSame(2611001, $this->nextPf(), 'PF has its own suffix, untouched by SF');
    }

    public function test_creating_records_through_the_forms_after_the_month_change(): void
    {
        $user = $this->makeUser('Windy');
        $this->setCounter('SF', '2610', 120);
        $this->setCounter('PF', '2610', 30);

        $this->travelTo($this->at('2026-11-02 10:00:00'));

        $this->actingAs($user)->post('/sf', $this->sfPayload());
        $this->actingAs($user)->post('/pf', $this->pfPayload());

        $this->assertSame(2611001, SfRecord::sole()->sf_number);
        $this->assertSame(2611001, PfRecord::sole()->pf_number);
    }

    public function test_the_suffix_range_ends_at_999_and_the_failed_attempt_does_not_move_the_counter(): void
    {
        $this->travelTo($this->at('2026-11-20 10:00:00'));
        $this->setCounter('SF', '2611', 999); // 998 was the last one issued

        $this->assertSame(2611999, $this->nextSf());
        $afterLast = $this->nextNumber('SF');

        try {
            $this->nextSf();
            $this->fail('suffix 1000 should have been refused');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($afterLast, $this->nextNumber('SF'), 'the refused attempt must roll back');
    }
}
