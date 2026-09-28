<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Filters used by the admin SF / PF database pages.
 *
 * All values are bound as query parameters (no raw SQL with user input).
 */
trait FiltersDatabaseRecords
{
    /**
     * @param  array{date_from?: ?string, date_to?: ?string, user?: ?string, number?: ?string}  $filters
     * @param  string  $numberColumn  "sf_number" or "pf_number"
     */
    public function scopeFilter(Builder $query, array $filters, string $numberColumn): Builder
    {
        // Create Date range, inclusive on both ends, interpreted in APP_TIMEZONE
        // (the same timezone Create Date is displayed in).
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        if (! empty($filters['user'])) {
            $query->where('user', $filters['user']);
        }

        if (isset($filters['number']) && $filters['number'] !== '') {
            // Partial match, so "26090" finds every number containing it.
            // % and _ typed by the user are escaped so they match literally.
            $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['number']);
            // $numberColumn is a fixed column name passed by the model, never user input.
            $query->whereRaw("CAST({$numberColumn} AS TEXT) LIKE ? ESCAPE '\\'", ['%'.$needle.'%']);
        }

        return $query;
    }
}
