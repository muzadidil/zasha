<?php

namespace App\Http\Middleware;

use App\Exceptions\Auth\AuthException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            throw AuthException::forbiddenRole($roles);
        }

        return $next($request);
    }
}
