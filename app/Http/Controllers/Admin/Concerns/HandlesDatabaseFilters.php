<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait HandlesDatabaseFilters
{
    /**
     * Read and validate the filter bar's query string.
     *
     * A bad value (e.g. a half-typed date) never breaks the page: that filter is
     * dropped and the message is shown above the table instead.
     *
     * @return array{0: array<string, ?string>, 1: \Illuminate\Support\MessageBag}
     */
    protected function databaseFilters(Request $request): array
    {
        $input = [
            'date_from' => $this->normalizeDate($this->cleanFilter($request->query('date_from'))),
            'date_to' => $this->normalizeDate($this->cleanFilter($request->query('date_to'))),
            'user' => $this->cleanFilter($request->query('user')),
            'number' => $this->cleanFilter($request->query('number')),
        ];

        $validator = Validator::make($input, [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'user' => ['nullable', 'string', 'max:50'],
            'number' => ['nullable', 'string', 'max:30'],
        ], [
            'date_from.date_format' => 'The "From" date is not a valid date.',
            'date_to.date_format' => 'The "To" date is not a valid date.',
        ]);

        $errors = $validator->errors();

        foreach ($errors->keys() as $key) {
            $input[$key] = null;
        }

        if ($input['date_from'] && $input['date_to'] && $input['date_from'] > $input['date_to']) {
            $errors->add('date_to', 'The "To" date must not be before the "From" date.');
            $input['date_from'] = $input['date_to'] = null;
        }

        return [$input, $errors];
    }

    /** @return list<string> every username, for the User dropdown */
    protected function allUsernames(): array
    {
        return User::query()->orderBy('username')->pluck('username')->all();
    }

    /**
     * Accepts YYYY-MM-DD as well as YYYY/MM/DD and DD/MM/YYYY (also with "-" or "."),
     * and returns YYYY-MM-DD. Anything unrecognised is passed through so validation reports it.
     */
    private function normalizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return $value;
    }

    private function cleanFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null; // ignores arrays such as ?user[]=x
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
