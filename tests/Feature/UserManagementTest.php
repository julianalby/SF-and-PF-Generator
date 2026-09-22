<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private function newUser(array $overrides = []): array
    {
        return array_merge([
            'username' => 'NewPerson',
            'role' => 'User',
            'password' => 'long-enough-1',
            'password_confirmation' => 'long-enough-1',
        ], $overrides);
    }

    public function test_admin_sees_the_user_list_with_no_username_and_role(): void
    {
        $this->makeUser('Windy');
        $admin = $this->makeAdmin('Finance');

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSeeInOrder(['No', 'Username', 'Role'])
            ->assertSeeInOrder(['Windy', 'User'])
            ->assertSeeInOrder(['Finance', 'Admin']);

        $this->assertSame([1, 2], $this->actingAs($admin)->get('/admin/users')->viewData('users')->pluck('id')->all());
    }

    public function test_admin_can_create_a_user_and_the_password_is_hashed(): void
    {
        $this->actingAs($this->makeAdmin())->post('/admin/users', $this->newUser())
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $user = User::where('username', 'NewPerson')->sole();
        $this->assertSame('User', $user->role);

        $stored = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertNotSame('long-enough-1', $stored);
        $this->assertSame('argon2id', password_get_info($stored)['algoName']);
        $this->assertTrue(Hash::check('long-enough-1', $stored));
    }

    public function test_admin_can_create_another_admin(): void
    {
        $this->actingAs($this->makeAdmin())->post('/admin/users', $this->newUser(['username' => 'Boss', 'role' => 'Admin']));

        $this->assertTrue(User::where('username', 'Boss')->sole()->isAdmin());
    }

    public function test_the_new_user_can_log_in(): void
    {
        $this->actingAs($this->makeAdmin())->post('/admin/users', $this->newUser());
        $this->post('/logout');

        $this->post('/login', ['username' => 'NewPerson', 'password' => 'long-enough-1'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    #[DataProvider('invalidNewUsers')]
    public function test_invalid_new_users_are_rejected(array $overrides, string $errorField): void
    {
        $this->makeUser('Existing');

        $this->actingAs($this->makeAdmin())->post('/admin/users', $this->newUser($overrides))
            ->assertSessionHasErrors($errorField);

        $this->assertSame(2, User::count());
    }

    public static function invalidNewUsers(): array
    {
        return [
            'username missing' => [['username' => ''], 'username'],
            'username taken' => [['username' => 'Existing'], 'username'],
            'username taken (different case)' => [['username' => 'eXISTING'], 'username'],
            'username too short' => [['username' => 'ab'], 'username'],
            'username too long' => [['username' => str_repeat('a', 51)], 'username'],
            'username with spaces' => [['username' => 'two words'], 'username'],
            'username with html' => [['username' => '<b>x</b>'], 'username'],
            'role missing' => [['role' => ''], 'role'],
            'role unknown' => [['role' => 'Superuser'], 'role'],
            'role wrong case' => [['role' => 'admin'], 'role'],
            'password missing' => [['password' => '', 'password_confirmation' => ''], 'password'],
            'password too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'password not confirmed' => [['password_confirmation' => 'different-1'], 'password'],
        ];
    }

    public function test_a_client_cannot_slip_in_extra_columns(): void
    {
        $this->actingAs($this->makeAdmin())->post('/admin/users', $this->newUser(['id' => 500, 'remember_token' => 'x', 'created_at' => '2000-01-01']));

        $user = User::where('username', 'NewPerson')->sole();
        $this->assertNotSame(500, $user->id);
        $this->assertNull($user->remember_token);
        $this->assertGreaterThan(2000, $user->created_at->year);
    }

    public function test_admin_can_reset_a_password_and_it_is_hashed(): void
    {
        $target = $this->makeUser('Tohal');
        $this->actingAs($this->makeAdmin())
            ->get('/admin/users/'.$target->id.'/edit')->assertOk()->assertSee('Tohal');

        $this->put('/admin/users/'.$target->id.'/password', ['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertRedirect(route('admin.users.index'));

        $stored = DB::table('users')->where('id', $target->id)->value('password');
        $this->assertSame('argon2id', password_get_info($stored)['algoName']);
        $this->assertTrue(Hash::check('brand-new-pass', $stored));
        $this->assertFalse(Hash::check('password', $stored));
    }

    public function test_reset_password_is_validated(): void
    {
        $target = $this->makeUser('Tohal');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put('/admin/users/'.$target->id.'/password', ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->actingAs($admin)->put('/admin/users/'.$target->id.'/password', ['password' => 'long-enough-1', 'password_confirmation' => 'nope'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $target->fresh()->password));
    }

    public function test_editing_an_unknown_user_is_a_404(): void
    {
        $this->actingAs($this->makeAdmin())->get('/admin/users/999/edit')->assertNotFound();
    }

    public function test_normal_users_cannot_reach_user_management(): void
    {
        $user = $this->makeUser('Windy');

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->post('/admin/users', $this->newUser())->assertForbidden();
        $this->actingAs($user)->put('/admin/users/1/password', ['password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1'])->assertForbidden();

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
