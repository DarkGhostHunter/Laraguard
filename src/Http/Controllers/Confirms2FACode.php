<?php

namespace DarkGhostHunter\Laraguard\Http\Controllers;

use DarkGhostHunter\Laraguard\Http\Requests\TotpConfirmRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

use function view;
use function response;
use function redirect;

trait Confirms2FACode
{
    /**
     * Display the TOTP code confirmation view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function showConfirmForm(): View
    {
        return view('laraguard::confirm');
    }

    /**
     * Confirm the given user's TOTP code.
     *
     * @param  \DarkGhostHunter\Laraguard\Http\Requests\TotpConfirmRequest  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function confirm(TotpConfirmRequest $request): JsonResponse|Response|RedirectResponse
    {
        return $request->wantsJson()
            ? response()->noContent()
            : redirect()->intended($this->redirectPath());
    }

    /**
     * Return the path to redirect if no intended path exists.
     *
     * @return string
     * @see \Illuminate\Foundation\Auth\RedirectsUsers
     */
    public function redirectPath(): string
    {
        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo();
        }

        return property_exists($this, 'redirectTo') ? $this->redirectTo : '/home';
    }
}
