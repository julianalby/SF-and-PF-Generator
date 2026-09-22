<?php

namespace Tests;

use App\Models\NumberSequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** A normal user. The factory password is "password". */
    protected function makeUser(string $username = 'Windy', string $role = User::ROLE_USER): User
    {
        return User::factory()->create(['username' => $username, 'role' => $role]);
    }

    protected function makeAdmin(string $username = 'Finance'): User
    {
        return $this->makeUser($username, User::ROLE_ADMIN);
    }

    protected function sfPayload(array $overrides = []): array
    {
        return array_merge([
            'vnid' => 'VN000123',
            'customer_name' => 'PT Contoh Jaya',
            'service' => 'Dedicated Internet 100 Mbps',
            'submission_token' => (string) Str::uuid(),
        ], $overrides);
    }

    protected function pfPayload(array $overrides = []): array
    {
        return array_merge([
            'project_name' => 'Fiber Expansion Phase 1',
            'submission_token' => (string) Str::uuid(),
        ], $overrides);
    }

    /** The number the NEXT record of this sequence will receive. */
    protected function nextNumber(string $key): int
    {
        return (int) NumberSequence::where('key', $key)->value('next_number');
    }
}
