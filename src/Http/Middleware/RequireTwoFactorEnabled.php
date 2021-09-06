<?php

namespace DarkGhostHunter\Laraguard\Http\Middleware;

use Closure;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use function response;
use function trans;
use function auth;

class RequireTwoFactorEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $redirectToRoute
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|mixed
     */
    public function handle($request, Closure $next, string $redirectToRoute = '2fa.notice')
    {
        if ($this->hasTwoFactorAuthDisabled()) {
            return $request->expectsJson()
                ? response()->json(['message' => trans('laraguard::messages.enable')], 403)
                : response()->redirectToRoute($redirectToRoute);
        }

        return $next($request);
    }

    /**
     * Check if the user has Two-Factor Authentication enabled.
     *
     * @return bool
     */
    protected function hasTwoFactorAuthDisabled(): bool
    {
        $user = auth()->user();

        return $user instanceof TwoFactorAuthenticatable && ! $user->hasTwoFactorEnabled();
    }
}
