<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roles and their permissions (T19). Administrators only. The built-in
 * Administrator role always has every permission and cannot be changed.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('roles/Index', [
            'roles' => Role::query()->withCount('users')->orderByDesc('is_system')->orderBy('name')->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'is_system' => $role->is_system,
                    'users_count' => $role->users_count,
                    'permissions' => $role->effectivePermissions(),
                ]),
            'groups' => config('permissions.groups'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/Form', ['role' => null, 'groups' => config('permissions.groups')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = Role::query()->create($request->validate($this->rules()));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} created."]);

        return to_route('admin.roles.index');
    }

    public function edit(Role $role): Response|RedirectResponse
    {
        if ($role->is_system) {
            return $this->refuse('The Administrator role always has full access and cannot be edited.');
        }

        return Inertia::render('roles/Form', [
            'role' => $role->only(['id', 'name', 'description', 'permissions']),
            'groups' => config('permissions.groups'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return $this->refuse('The Administrator role always has full access and cannot be edited.');
        }

        $role->update($request->validate($this->rules($role)));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role updated.']);

        return to_route('admin.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return $this->refuse('The Administrator role cannot be deleted.');
        }

        if ($count = $role->users()->count()) {
            return $this->refuse("Move the {$count} ".str('user')->plural($count).' with this role to another role first.');
        }

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role deleted.']);

        return to_route('admin.roles.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Role $role = null): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique(Role::class)->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Role::allPermissions())],
        ];
    }

    private function refuse(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('admin.roles.index');
    }
}
