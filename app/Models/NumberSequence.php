<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per independent sequence ("SF", "PF").
 * `next_number` is the number the next record of that kind will receive.
 * Never touch it directly: go through App\Services\SequenceService.
 */
class NumberSequence extends Model
{
    public const SF = 'SF';

    public const PF = 'PF';

    protected $fillable = [
        'key',
        'next_number',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }
}
