<?php

namespace App\Models;

use Database\Factories\SfRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SfRecord extends Model
{
    /** @use HasFactory<SfRecordFactory> */
    use HasFactory;

    /**
     * Only what the user types is mass-assignable. `user`, `sf_number` and
     * `submission_token` are set explicitly by FormRecordService (forceFill),
     * so request input can never write them.
     */
    protected $fillable = [
        'vnid',
        'customer_name',
        'service',
    ];

    protected function casts(): array
    {
        return [
            'sf_number' => 'integer',
        ];
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user === $user->username;
    }
}
