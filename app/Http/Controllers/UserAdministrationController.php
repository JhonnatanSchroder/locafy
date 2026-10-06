<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserAdministrationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $companyId = $request->user()?->company_id;

        return Inertia::render('admin/users/Index', [
            'users' => User::query()
                ->where('company_id', $companyId)
                ->latest('active')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (User $user): array => $this->userData($user)),
            'roles' => $this->roleOptions(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in([UserRole::Admin->value, UserRole::Operator->value])],
            'generate_password' => ['required', 'boolean'],
            'password' => ['required_if:generate_password,false', 'nullable', 'string', Password::default(), 'confirmed'],
        ]);

        $password = $data['generate_password'] ? str()->password(14) : $data['password'];
        unset($data['generate_password'], $data['password'], $data['password_confirmation']);

        User::query()->create([
            ...$data,
            'company_id' => $request->user()->company_id,
            'password' => Hash::make($password),
            'active' => true,
            'must_change_password_at' => now(),
            'created_by_user_id' => $request->user()->id,
            'role_updated_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário criado com sucesso.']);
        if ($request->boolean('generate_password')) {
            Inertia::flash('temporaryPassword', $password);
        }

        return to_route('users.index');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManagedUser($request, $user, 'update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in([UserRole::Admin->value, UserRole::Operator->value])],
        ]);

        if ($user->role->value !== $data['role']) {
            $this->ensureNotLastActiveAdmin($user, $data['role']);
            $data['role_updated_by_user_id'] = $request->user()->id;
        }

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário atualizado com sucesso.']);

        return to_route('users.index');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManagedUser($request, $user, 'update');

        $user->update([
            'active' => true,
            'deactivated_at' => null,
            'deactivated_by_user_id' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acesso ativado.']);

        return to_route('users.index');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManagedUser($request, $user, 'deactivate');
        $this->ensureNotLastActiveAdmin($user, UserRole::Operator->value);

        $user->tokens()->delete();
        $user->update([
            'active' => false,
            'deactivated_at' => now(),
            'deactivated_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acesso desativado.']);

        return to_route('users.index');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManagedUser($request, $user, 'resetPassword');

        $data = $request->validate([
            'generate_password' => ['required', 'boolean'],
            'password' => ['required_if:generate_password,false', 'nullable', 'string', Password::default(), 'confirmed'],
        ]);

        $password = $data['generate_password'] ? str()->password(14) : $data['password'];

        $user->tokens()->delete();
        $user->update([
            'password' => Hash::make($password),
            'must_change_password_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Senha redefinida.']);
        if ($request->boolean('generate_password')) {
            Inertia::flash('temporaryPassword', $password);
        }

        return to_route('users.index');
    }

    private function authorizeManagedUser(Request $request, User $user, string $ability): void
    {
        abort_unless($user->company_id === $request->user()?->company_id, 404);

        Gate::authorize($ability, $user);
    }

    private function ensureNotLastActiveAdmin(User $user, string $newRole): void
    {
        if ($user->role !== UserRole::Admin || $user->active === false || $newRole === UserRole::Admin->value) {
            return;
        }

        $activeAdmins = User::query()
            ->where('company_id', $user->company_id)
            ->where('active', true)
            ->where('role', UserRole::Admin->value)
            ->whereKeyNot($user->id)
            ->exists();

        if (! $activeAdmins) {
            throw ValidationException::withMessages([
                'role' => 'Não é permitido remover o último administrador ativo da empresa.',
            ]);
        }
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'active' => $user->active,
            'status_label' => $user->active ? 'Ativo' : 'Inativo',
        ];
    }

    private function roleOptions(): array
    {
        return [
            ['value' => UserRole::Admin->value, 'label' => UserRole::Admin->label()],
            ['value' => UserRole::Operator->value, 'label' => UserRole::Operator->label()],
        ];
    }
}
