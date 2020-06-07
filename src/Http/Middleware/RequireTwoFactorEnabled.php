<?php

namespace DarkGhostHunter\Laraguard\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Routing\ResponseFactory;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;

class RequireTwoFactorEnabled
{
    /**
     * Current User authenticated.
     *
     * @var \Illuminate\Contracts\Auth\Authenticatable|\DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable
     */
    protected $user;

    /**
     * Response Factory.
     *
     * @var \Illuminate\Contracts\Routing\ResponseFactory
     */
    protected $response;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  \Illuminate\Contracts\Routing\ResponseFactory  $response
     */
    public function __construct(Authenticatable $user, ResponseFactory $response)
    {
        $this->user = $user;
        $this->response = $response;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $redirectToRoute
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|mixed
     */
    public function handle($request, Closure $next, $redirectToRoute = '2fa.confirm')
    {
        if ($this->userHasTwoFactorEnabled()) {
            return $request->expectsJson()
                ? $this->response->json(['message' => trans('laraguard::messages.enable')], 403)
                : $this->response->redirectToRoute($redirectToRoute);
        }

        return $next($request);
    }

    /**
     * Check if the user has Two Factor Authentication enabled.
     *
     * @return bool
     */
    protected function userHasTwoFactorEnabled()
    {
        return $this->user instanceof TwoFactorAuthenticatable && $this->user->hasTwoFactorEnabled();
    }
}
