<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** T19: roles and permissions (docs/02-build-prompt.md §14.1). */
class RolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, RoleSeeder::class]);
    }

    private function userWithRole(string $name): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('name', $name)->value('id')]);
    }

    public function test_every_admin_route_is_behind_a_permission()
    {
        $open = ['dashboard', 'profile.', 'security.', 'appearance.', 'password.', 'user-password.', 'login', 'logout'];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($route->uri(), 'admin') || str_starts_with($route->uri(), 'admin/account') || collect($open)->contains(fn ($o) => str_starts_with($name, $o)) || ! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            $this->assertNotEmpty(
                array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'can:')),
                "Route {$route->uri()} ({$name}) has no permission check",
            );
        }
    }

    public function test_seeded_roles_and_the_administrator_role()
    {
        $this->assertEqualsCanonicalizing(['Administrator', 'Content Editor', 'Sales', 'SEO Manager'], Role::query()->pluck('name')->all());
        $this->assertSame(Role::allPermissions(), Role::administrator()->effectivePermissions());

        $admin = User::factory()->create();
        $this->assertTrue($admin->isAdministrator());
        $this->assertTrue($admin->can('administrator'));
    }

    public function test_an_editor_sees_only_content_modules()
    {
        $this->actingAs($this->userWithRole('Content Editor'));

        $this->get(route('admin.pages.index'))->assertOk();
        $this->get(route('admin.posts.index'))->assertOk();
        $this->getJson(route('admin.media.browse'))->assertOk(); // image picker inside editors
        $this->postJson(route('admin.seo.check'), ['type' => 'page', 'title' => 'x'])->assertSuccessful();

        foreach (['admin.users.index', 'admin.roles.index', 'admin.settings.index', 'admin.leads.index', 'admin.messages.index', 'admin.seo.report'] as $name) {
            $this->get(route($name))
                ->assertRedirect(route('dashboard'))
                ->assertInertiaFlash('toast.message', 'Your role does not include access to that page.');
        }

        $this->postJson(route('admin.static.rebuild'))->assertForbidden();
        $this->postJson(route('admin.seo.redirects.store'), ['from' => '/a', 'to' => '/b'])->assertForbidden();

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('stats', null)
            ->where('latestLeads', null)
            ->where('staticSite', null)
            ->where('hiddenFromGoogle', null)
            ->where('access.role', 'Content Editor')
            ->where('access.administrator', false)
            ->where('access.content', true)
            ->where('inbox', ['leads' => null, 'messages' => null]));
    }

    public function test_sales_works_leads_but_not_content()
    {
        $lead = Lead::factory()->create();
        $this->actingAs($this->userWithRole('Sales'));

        $this->get(route('admin.leads.index'))->assertOk();
        $this->get(route('admin.leads.export'))->assertOk();
        $this->patch(route('admin.leads.update', $lead), ['status' => 'contacted'])->assertSessionHasNoErrors();
        $this->get(route('admin.pages.index'))->assertRedirect(route('dashboard'));
        $this->getJson(route('admin.media.browse'))->assertForbidden();

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.leadsToday', 1)
            ->has('latestLeads', 1)
            ->where('hiddenFromGoogle', null));
    }

    public function test_viewing_leads_does_not_allow_changing_or_exporting_them()
    {
        $lead = Lead::factory()->create();
        $this->actingAs(User::factory()->withPermissions(['leads.view'])->create());

        $this->get(route('admin.leads.show', $lead))->assertOk();
        $this->patchJson(route('admin.leads.update', $lead), ['status' => 'lost'])->assertForbidden();
        $this->deleteJson(route('admin.leads.destroy', $lead))->assertForbidden();
        $this->get(route('admin.leads.export'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('stats.unreadMessages', null));
    }

    public function test_administrators_manage_roles()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.roles.index'))->assertInertia(fn (Assert $page) => $page->component('roles/Index')->has('roles', 4));

        $this->post(route('admin.roles.store'), ['name' => 'Reviews Only', 'description' => null, 'permissions' => ['reviews', 'faqs']])
            ->assertRedirect(route('admin.roles.index'));
        $role = Role::query()->where('name', 'Reviews Only')->sole();
        $this->assertSame(['reviews', 'faqs'], $role->permissions);

        $this->post(route('admin.roles.store'), ['name' => 'Bad', 'permissions' => ['users']])->assertSessionHasErrors('permissions.0');
        $this->post(route('admin.roles.store'), ['name' => 'Reviews Only', 'permissions' => []])->assertSessionHasErrors('name');

        $this->put(route('admin.roles.update', $role), ['name' => 'Reviews', 'permissions' => []])->assertSessionHasNoErrors();
        $this->assertSame([], $role->fresh()->permissions);

        $system = Role::administrator();
        $this->put(route('admin.roles.update', $system), ['name' => 'Boss', 'permissions' => []]);
        $this->assertSame('Administrator', $system->fresh()->name);
        $this->delete(route('admin.roles.destroy', $system));
        $this->assertModelExists($system);

        $used = Role::query()->where('name', 'Sales')->sole();
        User::factory()->create(['role_id' => $used->id]);
        $this->delete(route('admin.roles.destroy', $used))->assertInertiaFlash('toast.type', 'error');
        $this->assertModelExists($used);

        $this->delete(route('admin.roles.destroy', $role->fresh()));
        $this->assertModelMissing($role);
    }

    public function test_user_roles_and_safeguards()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);
        $editor = Role::query()->where('name', 'Content Editor')->value('id');

        $this->post(route('admin.users.store'), ['name' => 'Ed', 'email' => 'ed@example.com', 'password' => 'Str0ng-pass-123!', 'password_confirmation' => 'Str0ng-pass-123!'])
            ->assertSessionHasErrors('role_id');
        $this->post(route('admin.users.store'), ['name' => 'Ed', 'email' => 'ed@example.com', 'role_id' => $editor, 'password' => 'Str0ng-pass-123!', 'password_confirmation' => 'Str0ng-pass-123!'])
            ->assertSessionHasNoErrors();
        $ed = User::query()->where('email', 'ed@example.com')->sole();
        $this->assertSame('Content Editor', $ed->role->name);

        // You cannot change your own role, and the last Administrator stays one.
        $this->put(route('admin.users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role_id' => $editor])
            ->assertInertiaFlash('toast.message', 'You cannot change your own role.');
        $this->assertTrue($admin->fresh()->isAdministrator());

        $other = User::factory()->create(['role_id' => $editor]);
        $this->put(route('admin.users.update', $other), ['name' => $other->name, 'email' => $other->email, 'role_id' => Role::administrator()->id])->assertSessionHasNoErrors();
        $this->assertTrue($other->fresh()->isAdministrator());

        $this->actingAs($other->fresh());
        $this->put(route('admin.users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role_id' => $editor])->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->isAdministrator());
        $this->delete(route('admin.users.destroy', $admin))->assertSessionHasNoErrors();

        $this->get(route('admin.users.index'))->assertInertia(fn (Assert $page) => $page->has('users.0.role.name'));
    }

    public function test_the_last_administrator_cannot_be_demoted_or_deleted()
    {
        $admin = User::factory()->create();
        $target = User::factory()->create(['role_id' => Role::query()->where('name', 'Sales')->value('id')]);
        $this->actingAs($admin);

        // Make $target the only other admin, then remove $admin's admin rights via $target.
        $this->put(route('admin.users.update', $target), ['name' => $target->name, 'email' => $target->email, 'role_id' => Role::administrator()->id]);
        $this->actingAs($target->fresh());
        $this->delete(route('admin.users.destroy', $admin));
        $this->assertModelMissing($admin);

        // $target is the last Administrator: nobody can demote or delete them.
        $this->put(route('admin.users.update', $target), ['name' => $target->name, 'email' => $target->email, 'role_id' => Role::query()->where('name', 'Sales')->value('id')])
            ->assertInertiaFlash('toast.type', 'error');
        $this->assertTrue($target->fresh()->isAdministrator());
    }
}
