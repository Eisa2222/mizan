<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Usage: ->middleware('role:LegalCounsel')
     * Allows the given role and any HIGHER rank.
     */
    public function handle(Request $request, Closure $next, string $minRole): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $required = UserRole::tryFrom($minRole);
        if (! $required) {
            abort(500, "Unknown role: {$minRole}");
        }

        if (! $user->hasAtLeastRole($required)) {
            abort(403, 'ليس لديك صلاحية للوصول');
        }

        return $next($request);
    }
}
