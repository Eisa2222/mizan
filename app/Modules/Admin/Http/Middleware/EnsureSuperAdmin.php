<?php

namespace Modules\Admin\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->hasRole(UserRole::SuperAdmin),
            403,
            __('admin.errors.super_admin_only'),
        );

        return $next($request);
    }
}
