<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Front-of-stack middleware for every /admin request:
 *  - locale → `ru` (Orchid is a Russian UI, API stays on APP_LOCALE=en),
 *  - default auth guard → `platform` (so RedirectIfAuthenticated on /admin/login
 *    checks the Orchid session, not the SPA's web session, which would loop guests
 *    logged into /app back to /admin/boards → login → …).
 */
class SetOrchidLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale('ru');
        Auth::shouldUse('platform');

        return $next($request);
    }
}
