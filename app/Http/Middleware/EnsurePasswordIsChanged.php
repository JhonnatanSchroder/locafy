<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->mustChangePassword()) {
            return $next($request);
        }

        if ($request->is('api/v1/me') || $request->is('api/v1/auth/logout')) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            abort(403, 'Troca de senha obrigatória.');
        }

        if ($request->routeIs('password.change.edit', 'password.change.update', 'logout')) {
            return $next($request);
        }

        return redirect()->route('password.change.edit');
    }
}
