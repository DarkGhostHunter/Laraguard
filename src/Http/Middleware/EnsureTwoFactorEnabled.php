<?php

namespace DarkGhostHunter\Laraguard\Http\Middleware;

use Closure;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use DarkGhostHunter\Laraguard\Http\Controllers\TwoFactorAuthenticationController;

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
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        $user = $request->user();

        if ($user instanceof TwoFactorAuthenticatable && $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return abort(403, __('Two Factor Authentication is not enabled.'));
        }

        if ($redirectToRoute) {
            return redirect()->route($redirectToRoute);
        }

        return redirect()->action(TwoFactorAuthenticationController::class . '@notice');
    }
}
