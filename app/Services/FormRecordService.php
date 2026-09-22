<?php

namespace App\Services;

use App\Models\NumberSequence;
use App\Models\PfRecord;
use App\Models\SfRecord;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates SF / PF records. The number and the `user` column are always set
 * here, on the server: nothing coming from the request can influence them.
 */
class FormRecordService
{
    private const ATTEMPTS = 5;

    private const TOKEN_ERROR = 'This form is no longer valid. Please reload the page and try again.';

    public function __construct(private readonly SequenceService $sequences) {}

    /**
     * @param  array{vnid: string, customer_name: string, service: string}  $data
     * @param  string  $token  idempotency key rendered with the form; submitting
     *                         the same form twice returns the first record.
     */
    public function createSf(User $user, array $data, string $token): SfRecord
    {
        if ($existing = $this->existing(SfRecord::class, $token, $user)) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($user, $data, $token): SfRecord {
                // Take the next number (this also advances the counter) ...
                $number = $this->sequences->next(NumberSequence::SF);

                // ... and assign it to the record, in the same transaction.
                $record = new SfRecord($data);
                $record->forceFill([
                    'user' => $user->username,
                    'sf_number' => $number,
                    'submission_token' => $token,
                ])->save();

                return $record;
            }, self::ATTEMPTS);
        } catch (UniqueConstraintViolationException $e) {
            return $this->recoverDuplicate(SfRecord::class, $token, $user, $e);
        }
    }

    /**
     * @param  array{project_name: string, sf_number?: ?string, vnid?: ?string, customer_name?: ?string}  $data
     */
    public function createPf(User $user, array $data, string $token): PfRecord
    {
        if ($existing = $this->existing(PfRecord::class, $token, $user)) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($user, $data, $token): PfRecord {
                $number = $this->sequences->next(NumberSequence::PF);

                $record = new PfRecord($data);
                $record->forceFill([
                    'user' => $user->username,
                    'pf_number' => $number,
                    'submission_token' => $token,
                ])->save();

                return $record;
            }, self::ATTEMPTS);
        } catch (UniqueConstraintViolationException $e) {
            return $this->recoverDuplicate(PfRecord::class, $token, $user, $e);
        }
    }

    /**
     * Two identical submissions raced and this one lost on the UNIQUE
     * submission_token. Its transaction, including the counter increment, was
     * rolled back, so no number was wasted: hand back the winner's record.
     * Any other unique violation (e.g. a tampered sequence) is a real problem
     * and is re-thrown rather than hidden.
     *
     * @param  class-string<SfRecord|PfRecord>  $model
     */
    private function recoverDuplicate(string $model, string $token, User $user, UniqueConstraintViolationException $e): SfRecord|PfRecord
    {
        return $this->existing($model, $token, $user) ?? throw $e;
    }

    /**
     * @param  class-string<SfRecord|PfRecord>  $model
     */
    private function existing(string $model, string $token, User $user): SfRecord|PfRecord|null
    {
        $record = $model::query()->where('submission_token', $token)->first();

        if ($record === null) {
            return null;
        }

        if (! $record->isOwnedBy($user)) {
            // A token that belongs to somebody else's record: never reveal or reuse it.
            throw ValidationException::withMessages(['submission_token' => self::TOKEN_ERROR]);
        }

        return $record;
    }
}
