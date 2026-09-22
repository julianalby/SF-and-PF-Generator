<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_page_uses_username_and_password_only(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Username')
            ->assertSee('Password')
            ->assertDontSee('E-mail')
            ->assertDontSee('Email');
    }

    public function test_users_table_has_a_username_and_no_email(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'email'));
        $this->assertFalse(Schema::hasColumn('users', 'name'));

        foreach (['id', 'username', 'password', 'role', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column), "users.$column is missing");
        }
    }

    #[DataProvider('protectedGetUrls')]
    public function test_guests_are_redirected_to_login(string $url): void
    {
        $this->get($url)->assertRedirect(route('login'));
    }

    public static function protectedGetUrls(): array
    {
        return [
            'root' => ['/'],
            'dashboard' => ['/dashboard'],
            'sf form' => ['/sf/create'],
            'pf form' => ['/pf/create'],
            'sf result' => ['/sf/1'],
            'pf result' => ['/pf/1'],
            'sf database' => ['/admin/sf-records'],
            'pf database' => ['/admin/pf-records'],
            'users' => ['/admin/users'],
            'edit user' => ['/admin/users/1/edit'],
        ];
    }

    public function test_guests_cannot_submit_forms_or_create_users(): void
    {
        $this->post('/sf', $this->sfPayload())->assertRedirect(route('login'));
        $this->post('/pf', $this->pfPayload())->assertRedirect(route('login'));
        $this->post('/admin/users', ['username' => 'Sneaky', 'role' => 'Admin', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('sf_records', 0);
        $this->assertDatabaseCount('pf_records', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_can_log_in_with_username_and_password(): void
    {
        $this->makeUser('Windy');

        $this->post('/login', ['username' => 'Windy', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_username_is_not_case_sensitive(): void
    {
        $this->makeUser('Windy');

        $this->post('/login', ['username' => 'wINDY', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_wrong_password_and_unknown_user_get_the_same_generic_error(): void
    {
        $this->makeUser('Windy');

        $this->post('/login', ['username' => 'Windy', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['username' => 'These credentials do not match our records.']);
        $this->assertGuest();

        $this->post('/login', ['username' => 'Nobody', 'password' => 'whatever'])
            ->assertSessionHasErrors(['username' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_username_and_password_are_required(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['username', 'password']);
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_session_and_urls_are_protected_again(): void
    {
        $this->makeUser('Windy');
        $this->post('/login', ['username' => 'Windy', 'password' => 'password']);
        $this->get('/dashboard')->assertOk();

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/sf/create')->assertRedirect(route('login'));
    }

    public function test_signed_in_users_are_redirected_away_from_the_login_page(): void
    {
        $this->actingAs($this->makeUser())->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_root_sends_signed_in_users_to_the_dashboard(): void
    {
        $this->actingAs($this->makeUser())->get('/')->assertRedirect('/dashboard');
    }

    public function test_user_is_sent_back_to_the_page_they_asked_for(): void
    {
        $this->makeUser('Windy');

        $this->get('/pf/create')->assertRedirect(route('login'));
        $this->post('/login', ['username' => 'Windy', 'password' => 'password'])
            ->assertRedirect(route('pf.create'));
    }

    public function test_passwords_are_stored_as_argon2id_hashes(): void
    {
        $user = User::create(['username' => 'Tester', 'password' => 'secret-pass-123', 'role' => 'User']);

        $stored = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame('secret-pass-123', $stored);
        $this->assertSame('argon2id', password_get_info($stored)['algoName']);
        $this->assertTrue(Hash::check('secret-pass-123', $stored));
        $this->assertSame('argon2id', config('hashing.driver'));
    }

    public function test_hashes_from_another_algorithm_or_plaintext_cannot_log_in_and_do_not_crash(): void
    {
        $now = now();
        DB::table('users')->insert([
            ['username' => 'Bcrypted', 'password' => password_hash('secret-pass-123', PASSWORD_BCRYPT), 'role' => 'User', 'created_at' => $now, 'updated_at' => $now],
            ['username' => 'Plaintext', 'password' => 'secret-pass-123', 'role' => 'User', 'created_at' => $now, 'updated_at' => $now],
        ]);

        foreach (['Bcrypted', 'Plaintext'] as $username) {
            $this->post('/login', ['username' => $username, 'password' => 'secret-pass-123'])
                ->assertRedirect()
                ->assertSessionHasErrors('username');
            $this->assertGuest();
        }
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $this->makeUser('Windy');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'Windy', 'password' => 'wrong']);
        }

        // Even the correct password is refused while locked out.
        $this->post('/login', ['username' => 'Windy', 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('username'));
    }

    public function test_pages_are_never_cacheable_and_carry_security_headers(): void
    {
        $response = $this->actingAs($this->makeUser())->get('/dashboard');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_error_pages_render_without_a_session(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found');
    }
}
