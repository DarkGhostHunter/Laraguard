<?php

namespace DarkGhostHunter\Laraguard\Http\Middleware;

use Closure;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;

class EnsureTwoFactorEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $redirectToRoute
     * @return void|\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next, $redirectToRoute = '2fa.notice')
    {
        $user = $request->user();

        if ($user instanceof TwoFactorAuthenticatable && $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        return $request->expectsJson()
            ? abort(403, __('Two Factor Authentication is not enabled.'))
            : redirect()->route($redirectToRoute);
    }
}
