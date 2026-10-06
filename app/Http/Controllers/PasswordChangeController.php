<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordChangeController extends Controller
{
    use PasswordValidationRules;

    public function edit(): Response
    {
        return Inertia::render('auth/ChangePassword', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => $this->passwordRules(),
        ]);

        $request->user()->tokens()->delete();
        $request->user()->update([
            'password' => $validated['password'],
            'must_change_password_at' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Senha alterada com sucesso.']);

        return redirect()->intended(route('dashboard'));
    }
}
