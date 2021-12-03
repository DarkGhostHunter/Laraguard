<?php

namespace DarkGhostHunter\Laraguard\Http\Middleware;

use Closure;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use Illuminate\Http\Request;

use function response;
use function url;

class ConfirmTwoFactorCode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $redirectToRoute
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $redirectToRoute = '2fa.confirm'): mixed
    {
        if ($this->userHasNotEnabledTwoFactorAuth($request) || $this->codeWasValidated($request)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => trans('laraguard::messages.required')], 403)
            : response()->redirectGuest(url()->route($redirectToRoute));
    }

    /**
     * Check if the user is using Two-Factor Authentication.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function userHasNotEnabledTwoFactorAuth(Request $request): bool
    {
        return ! ($request->user() instanceof TwoFactorAuthenticatable && $request->user()->hasTwoFactorEnabled());
    }

    /**
     * Determine if the confirmation timeout has expired.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function codeWasValidated(Request $request): bool
    {
        $confirmedAt = now()->getTimestamp() - $request->session()->get('2fa.totp_confirmed_at', 0);

        return $confirmedAt < config('laraguard.confirm.timeout', 10800);
    }
}
