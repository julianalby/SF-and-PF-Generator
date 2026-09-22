<?php

namespace Tests\Feature;

use App\Models\NumberSequence;
use App\Models\PfRecord;
use App\Models\SfRecord;
use App\Models\User;
use App\Services\SequenceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class NumberSequenceTest extends TestCase
{
    public function test_the_migration_creates_two_independent_sequences_with_the_specified_start_numbers(): void
    {
        $this->assertSame(2, NumberSequence::count());
        $this->assertSame(26090119, $this->nextNumber('SF'));
        $this->assertSame(26090138, $this->nextNumber('PF'));
    }

    public function test_next_returns_consecutive_numbers_and_advances_only_its_own_counter(): void
    {
        $sequences = app(SequenceService::class);

        $numbers = DB::transaction(fn () => [
            $sequences->next('SF'),
            $sequences->next('SF'),
            $sequences->next('PF'),
            $sequences->next('SF'),
        ]);

        $this->assertSame([26090119, 26090120, 26090138, 26090121], $numbers);
        $this->assertSame(26090122, $this->nextNumber('SF'));
        $this->assertSame(26090139, $this->nextNumber('PF'));
    }

    public function test_a_rolled_back_transaction_gives_the_number_back(): void
    {
        $sequences = app(SequenceService::class);

        try {
            DB::transaction(function () use ($sequences) {
                $sequences->next('SF');
                $sequences->next('PF');

                throw new RuntimeException('rollback please');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(26090119, $this->nextNumber('SF'));
        $this->assertSame(26090138, $this->nextNumber('PF'));
    }

    public function test_it_refuses_to_run_outside_a_transaction(): void
    {
        DB::rollBack(); // leave the transaction RefreshDatabase wrapped around the test

        $this->expectException(LogicException::class);

        app(SequenceService::class)->next('SF');
    }

    public function test_a_missing_sequence_fails_loudly_instead_of_guessing(): void
    {
        NumberSequence::where('key', 'SF')->delete();

        $this->expectException(RuntimeException::class);

        DB::transaction(fn () => app(SequenceService::class)->next('SF'));
    }

    public function test_sf_and_pf_numbers_have_unique_database_constraints(): void
    {
        SfRecord::factory()->create(['sf_number' => 26090119]);
        PfRecord::factory()->create(['pf_number' => 26090138]);

        try {
            SfRecord::factory()->create(['sf_number' => 26090119]);
            $this->fail('duplicate sf_number was accepted');
        } catch (UniqueConstraintViolationException) {
            $this->assertTrue(true);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        PfRecord::factory()->create(['pf_number' => 26090138]);
    }

    public function test_usernames_are_unique_and_case_insensitive_in_the_database(): void
    {
        $this->makeUser('Windy');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->makeUser('WINDY');
    }

    public function test_the_sequence_keys_are_unique(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        NumberSequence::create(['key' => 'SF', 'next_number' => 1]);
    }

    public function test_the_creator_columns_are_not_mass_assignable(): void
    {
        $record = new SfRecord(['user' => 'Hacker', 'sf_number' => 1, 'submission_token' => 'x', 'vnid' => 'V']);

        $this->assertNull($record->user);
        $this->assertNull($record->sf_number);
        $this->assertSame('V', $record->vnid);

        $pf = new PfRecord(['user' => 'Hacker', 'pf_number' => 1, 'project_name' => 'P']);
        $this->assertNull($pf->user);
        $this->assertNull($pf->pf_number);
    }

    public function test_user_roles_are_the_two_documented_values(): void
    {
        $this->assertSame(['User', 'Admin'], User::ROLES);
    }
}
