<?php

namespace DarkGhostHunter\Laraguard\Http\Middleware;

use Closure;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Routing\ResponseFactory;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConfirmTwoFactorCode
{
    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Routing\ResponseFactory  $response
     * @param  \Illuminate\Contracts\Routing\UrlGenerator  $url
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     */
    public function __construct(
        protected ResponseFactory $response,
        protected UrlGenerator $url,
        protected ?Authenticatable $user = null)
    {
        //
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $redirectToRoute
     * @return mixed
     */
    public function handle($request, Closure $next, string $redirectToRoute = '2fa.confirm')
    {
        if ($this->userHasNotEnabledTwoFactorAuth() || $this->codeWasValidated($request)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? $this->response->json(['message' => trans('laraguard::messages.required')], 403)
            : $this->response->redirectGuest($this->url->route($redirectToRoute));
    }

    /**
     * Check if the user is using Two-Factor Authentication.
     *
     * @return bool
     */
    protected function userHasNotEnabledTwoFactorAuth(): bool
    {
        return ! ($this->user instanceof TwoFactorAuthenticatable && $this->user->hasTwoFactorEnabled());
    }

    /**
     * Determine if the confirmation timeout has expired.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function codeWasValidated(Request $request): bool
    {
        $confirmedAt = now()->timestamp - $request->session()->get('2fa.totp_confirmed_at', 0);

        return $confirmedAt < config('laraguard.confirm.timeout', 10800);
    }
}
