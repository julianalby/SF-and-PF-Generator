<?php

namespace App\Models;

use Database\Factories\PfRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PfRecord extends Model
{
    /** @use HasFactory<PfRecordFactory> */
    use HasFactory;

    /**
     * Only what the user types is mass-assignable. `user`, `pf_number` and
     * `submission_token` are set explicitly by FormRecordService (forceFill).
     * Note: `sf_number` here is the optional SF reference typed by the user,
     * not a generated number.
     */
    protected $fillable = [
        'project_name',
        'sf_number',
        'vnid',
        'customer_name',
    ];

    protected function casts(): array
    {
        return [
            'pf_number' => 'integer',
        ];
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user === $user->username;
    }
}
