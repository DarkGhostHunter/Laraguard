<?php

namespace DarkGhostHunter\Laraguard\Http\Middleware;

use Closure;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;

class ResolveTwoFactorAuthenticatable
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return void|\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next)
    {
        if (($user = $request->user()) instanceof TwoFactorAuthenticatable) {
            return app()->instance(TwoFactorAuthenticatable::class, $user);
        }

        return $next($request);
    }
}
