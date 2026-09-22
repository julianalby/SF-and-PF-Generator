<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side guard for every admin-only route. Runs after "auth", so guests
 * are redirected to the login page first; signed-in non-admins get a 403.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isAdmin(), 403, 'You do not have permission to access this page.');

        return $next($request);
    }
}
