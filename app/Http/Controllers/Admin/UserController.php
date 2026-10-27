<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin accounts, each with one role (T19). Administrators only. Nobody can
 * change their own role, and the last Administrator cannot be demoted or deleted.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('users/Index', [
            'users' => User::query()
                ->with('role:id,name,is_system')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role_id', 'last_login_at', 'created_at']),
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/Form', [
            'user' => null,
            'roles' => $this->roleOptions(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $user = User::query()->create($data);
        $user->forceFill(['email_verified_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "User {$user->name} created."]);

        return to_route('admin.users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        return Inertia::render('users/Form', [
            'user' => $user->only(['id', 'name', 'email', 'role_id']),
            'roles' => $this->roleOptions(),
            'isSelf' => $user->is($request->user()),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate($this->rules($user));

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ((int) $data['role_id'] !== $user->role_id) {
            if ($user->is($request->user())) {
                return $this->refuse('You cannot change your own role.');
            }

            if ($user->isAdministrator() && $this->administratorCount() <= 1) {
                return $this->refuse('The last Administrator must keep the Administrator role.');
            }
        }

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated.']);

        return to_route('admin.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return $this->refuse('You cannot delete your own account.');
        }

        if ($user->isAdministrator() && $this->administratorCount() <= 1) {
            return $this->refuse('The last Administrator account cannot be deleted.');
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User deleted.']);

        return to_route('admin.users.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::default(), 'confirmed'],
            'role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
        ];
    }

    /** @return list<array{id: int, name: string, description: ?string, is_system: bool}> */
    private function roleOptions(): array
    {
        return Role::query()->orderByDesc('is_system')->orderBy('name')->get(['id', 'name', 'description', 'is_system'])->toArray();
    }

    private function administratorCount(): int
    {
        return User::query()->whereRelation('role', 'is_system', true)->count();
    }

    private function refuse(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
