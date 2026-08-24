<?php

namespace App\Http\Middleware;

use App\Services\Rbac;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: `perm:module,action` (action defaults to "view"), plus the
 * special `perm:admin` which requires an is_admin role. Denies with 403.
 * Server-side counterpart to the @perm Blade directive — never rely on hidden
 * UI alone.
 */
class EnsurePermission
{
    public function __construct(protected Rbac $rbac) {}

    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        $allowed = $module === 'admin'
            ? $this->rbac->isAdmin()
            : $this->rbac->can($module, $action);

        if (! $allowed) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
