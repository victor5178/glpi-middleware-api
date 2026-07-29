<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks access unless the user has logged in via GLPI. */
class EnsureGlpiAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('glpi_user')) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
