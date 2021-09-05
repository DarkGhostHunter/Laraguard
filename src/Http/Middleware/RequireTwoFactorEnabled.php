<?php

namespace DarkGhostHunter\Laraguard\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Routing\ResponseFactory;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;

class RequireTwoFactorEnabled
{
    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  \Illuminate\Contracts\Routing\ResponseFactory  $response
     */
    public function __construct(protected ResponseFactory $response, protected ?Authenticatable $user = null)
    {
        //
    }

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
                ? $this->response->json(['message' => trans('laraguard::messages.enable')], 403)
                : $this->response->redirectToRoute($redirectToRoute);
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
        return $this->user instanceof TwoFactorAuthenticatable && ! $this->user->hasTwoFactorEnabled();
    }
}
