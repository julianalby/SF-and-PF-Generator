<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederAndCommandsTest extends TestCase
{
    public function test_seeder_creates_the_initial_users_with_the_right_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, User::count());
        $this->assertSame(['Alida', 'Windy', 'Aqiqah', 'Tohal', 'Adam'], User::where('role', 'User')->orderBy('id')->pluck('username')->all());
        $this->assertSame(['Finance'], User::where('role', 'Admin')->pluck('username')->all());
    }

    public function test_seeded_passwords_are_random_hashed_and_never_plaintext(): void
    {
        $this->seed(UserSeeder::class);

        $hashes = User::pluck('password')->all();
        foreach ($hashes as $hash) {
            $this->assertSame('argon2id', password_get_info($hash)['algoName']);
        }
        $this->assertCount(6, array_unique($hashes), 'every seeded user gets a different random password');
    }

    public function test_seeder_can_use_the_configured_development_password(): void
    {
        config(['sfpf.seed_password' => 'dev-only-secret']);
        $this->seed(UserSeeder::class);

        $this->assertTrue(Hash::check('dev-only-secret', User::where('username', 'Windy')->value('password')));

        $this->post('/login', ['username' => 'Finance', 'password' => 'dev-only-secret'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_seeding_again_never_touches_existing_users(): void
    {
        config(['sfpf.seed_password' => 'dev-only-secret']);
        $this->seed(UserSeeder::class);

        config(['sfpf.seed_password' => 'something-else']);
        $this->seed(UserSeeder::class);

        $this->assertSame(6, User::count());
        $this->assertTrue(Hash::check('dev-only-secret', User::where('username', 'Windy')->value('password')));
    }

    public function test_seeding_does_not_reset_the_number_sequences(): void
    {
        $this->actingAs($this->makeUser('Windy'))->post('/sf', $this->sfPayload());

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(26090120, $this->nextNumber('SF'));
    }

    public function test_user_create_command(): void
    {
        $this->artisan('user:create', ['username' => 'CmdUser', '--role' => 'admin', '--password' => 'cmd-password-1'])
            ->assertSuccessful();

        $user = User::where('username', 'CmdUser')->sole();
        $this->assertTrue($user->isAdmin());
        $this->assertSame('argon2id', password_get_info($user->password)['algoName']);
        $this->assertTrue(Hash::check('cmd-password-1', $user->password));
    }

    public function test_user_create_command_rejects_bad_input(): void
    {
        $this->makeUser('Taken');

        $this->artisan('user:create', ['username' => 'taken', '--password' => 'cmd-password-1'])->assertFailed();
        $this->artisan('user:create', ['username' => 'Fine', '--role' => 'Boss', '--password' => 'cmd-password-1'])->assertFailed();
        $this->artisan('user:create', ['username' => 'Fine', '--password' => 'short'])->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_user_password_command(): void
    {
        $user = $this->makeUser('Windy');

        $this->artisan('user:password', ['username' => 'windy', '--password' => 'reset-password-1'])->assertSuccessful();

        $this->assertTrue(Hash::check('reset-password-1', $user->fresh()->password));
        $this->assertSame('argon2id', password_get_info($user->fresh()->password)['algoName']);

        $this->artisan('user:password', ['username' => 'nobody', '--password' => 'reset-password-1'])->assertFailed();
    }
}
