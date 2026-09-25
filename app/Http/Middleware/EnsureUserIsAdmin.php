<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * Only authenticated users with the `admin` flag may continue.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->admin === true, 403);

        return $next($request);
    }
}
