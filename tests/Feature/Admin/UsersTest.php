<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_index_lists_admins()
    {
        User::factory()->create(['name' => 'Second Admin']);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/Index')
                ->has('users', 2)
                ->where('currentUserId', $this->admin->id)
                ->missing('users.0.password')
            );
    }

    public function test_an_admin_can_be_created()
    {
        $this->post(route('admin.users.store'), [
            'name' => 'New Admin',
            'email' => 'new@example.com',
            'role_id' => Role::administrator()->id,
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'new@example.com')->sole();
        $this->assertTrue(Hash::check('a-strong-password', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_create_validates_unique_email_and_password_confirmation()
    {
        $this->post(route('admin.users.store'), [
            'name' => 'Dup',
            'email' => $this->admin->email,
            'password' => 'a-strong-password',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_updating_without_a_password_keeps_the_current_one()
    {
        $other = User::factory()->create(['password' => 'original-password']);

        $this->put(route('admin.users.update', $other), [
            'name' => 'Renamed',
            'email' => $other->email,
            'role_id' => $other->role_id,
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('admin.users.index'));

        $other->refresh();
        $this->assertSame('Renamed', $other->name);
        $this->assertTrue(Hash::check('original-password', $other->password));
    }

    public function test_an_admin_cannot_delete_themselves()
    {
        User::factory()->create();

        $this->delete(route('admin.users.destroy', $this->admin))->assertRedirect();

        $this->assertModelExists($this->admin);
    }

    public function test_the_last_admin_cannot_be_deleted()
    {
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->admin->delete();

        $this->delete(route('admin.users.destroy', $other))->assertRedirect();

        $this->assertModelExists($other);
    }

    public function test_another_admin_can_be_deleted()
    {
        $other = User::factory()->create();

        $this->delete(route('admin.users.destroy', $other))->assertRedirect(route('admin.users.index'));

        $this->assertModelMissing($other);
    }

    public function test_logging_in_records_last_login_time()
    {
        auth()->logout();
        $user = User::factory()->create(['password' => 'login-password']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'login-password']);

        $this->assertNotNull($user->refresh()->last_login_at);
    }
}
