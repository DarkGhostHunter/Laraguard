<?php

namespace DarkGhostHunter\Laraguard\Http\Middleware;

use Closure;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

use function response;
use function trans;

class RequireTwoFactorEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $redirectToRoute
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $redirectToRoute = '2fa.notice'): mixed
    {
        if ($this->hasTwoFactorAuthDisabled($request->user())) {
            return $request->expectsJson()
                ? response()->json(['message' => trans('laraguard::messages.enable')], 403)
                : response()->redirectToRoute($redirectToRoute);
        }

        return $next($request);
    }

    /**
     * Check if the user has Two-Factor Authentication enabled.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @return bool
     */
    protected function hasTwoFactorAuthDisabled(?Authenticatable $user): bool
    {
        return $user instanceof TwoFactorAuthenticatable && ! $user->hasTwoFactorEnabled();
    }
}
