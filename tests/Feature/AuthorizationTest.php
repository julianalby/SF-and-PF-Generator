<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    #[DataProvider('adminEndpoints')]
    public function test_normal_users_are_denied_every_admin_endpoint(string $method, string $url): void
    {
        $user = $this->makeUser('Windy'); // id 1, so /admin/users/1/... resolves

        $this->actingAs($user)
            ->{$method}($url, ['username' => 'Sneaky', 'role' => 'Admin', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertForbidden();

        $this->assertSame(1, User::count(), 'a normal user must not be able to create users');
    }

    public static function adminEndpoints(): array
    {
        return [
            'sf database' => ['get', '/admin/sf-records'],
            'pf database' => ['get', '/admin/pf-records'],
            'user list' => ['get', '/admin/users'],
            'create user' => ['post', '/admin/users'],
            'edit user' => ['get', '/admin/users/1/edit'],
            'reset password' => ['put', '/admin/users/1/password'],
        ];
    }

    public function test_admins_can_open_the_admin_pages(): void
    {
        $admin = $this->makeAdmin();

        foreach (['/admin/sf-records', '/admin/pf-records', '/admin/users', '/admin/users/'.$admin->id.'/edit'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_normal_users_can_use_both_forms(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/sf/create')->assertOk();
        $this->actingAs($user)->get('/pf/create')->assertOk();
    }

    public function test_admins_can_also_use_both_forms(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/sf/create')->assertOk();
        $this->actingAs($admin)->get('/pf/create')->assertOk();
    }

    public function test_navigation_for_a_normal_user(): void
    {
        $this->actingAs($this->makeUser('Windy'))->get('/dashboard')
            ->assertOk()
            ->assertSee('Logged in as:')
            ->assertSee('Windy')
            ->assertSee('Generate SF')
            ->assertSee('Generate PF')
            ->assertSee('Logout')
            ->assertDontSee('SF Database')
            ->assertDontSee('PF Database')
            ->assertDontSee('User Management');
    }

    public function test_navigation_for_an_admin(): void
    {
        $this->actingAs($this->makeAdmin('Finance'))->get('/dashboard')
            ->assertOk()
            ->assertSee('Logged in as:')
            ->assertSee('Finance')
            ->assertSee('Generate SF')
            ->assertSee('Generate PF')
            ->assertSee('SF Database')
            ->assertSee('PF Database')
            ->assertSee('User Management')
            ->assertSee('Logout');
    }

    public function test_role_check_ignores_case_and_fails_closed(): void
    {
        $now = now();
        DB::table('users')->insert([
            ['username' => 'lowerAdmin', 'password' => 'x', 'role' => 'admin', 'created_at' => $now, 'updated_at' => $now],
            ['username' => 'spacedAdmin', 'password' => 'x', 'role' => ' ADMIN ', 'created_at' => $now, 'updated_at' => $now],
            ['username' => 'typo', 'password' => 'x', 'role' => 'Adm1n', 'created_at' => $now, 'updated_at' => $now],
            ['username' => 'blank', 'password' => 'x', 'role' => '', 'created_at' => $now, 'updated_at' => $now],
            ['username' => 'plain', 'password' => 'x', 'role' => 'User', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->assertTrue(User::where('username', 'lowerAdmin')->first()->isAdmin());
        $this->assertTrue(User::where('username', 'spacedAdmin')->first()->isAdmin());
        $this->assertFalse(User::where('username', 'typo')->first()->isAdmin());
        $this->assertFalse(User::where('username', 'blank')->first()->isAdmin());
        $this->assertFalse(User::where('username', 'plain')->first()->isAdmin());

        // ... and the middleware really lets the lower-case admin in / keeps the typo out.
        $this->actingAs(User::where('username', 'lowerAdmin')->first())->get('/admin/users')->assertOk();
        $this->actingAs(User::where('username', 'typo')->first())->get('/admin/users')->assertForbidden();
    }

    public function test_role_is_normalised_when_saved_through_the_model(): void
    {
        $user = User::create(['username' => 'Normalised', 'password' => 'password123', 'role' => 'admin']);

        $this->assertSame('Admin', $user->fresh()->role);
    }
}
