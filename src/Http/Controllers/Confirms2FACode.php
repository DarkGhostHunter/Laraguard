<?php

namespace DarkGhostHunter\Laraguard\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use function view;
use function response;
use function redirect;
use function now;

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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function confirm(Request $request): JsonResponse|Response|RedirectResponse
    {
        $request->validate($this->rules(), $this->validationErrorMessages());

        $this->resetTotpConfirmationTimeout($request);

        return $request->wantsJson()
            ? response()->noContent()
            : redirect()->intended($this->redirectPath());
    }

    /**
     * Reset the TOTP code timeout.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function resetTotpConfirmationTimeout(Request $request): void
    {
        $request->session()->put('2fa.totp_confirmed_at', now()->timestamp);
    }

    /**
     * Get the TOTP code validation rules.
     *
     * @return array
     */
    protected function rules(): array
    {
        return [
            '2fa_code' => 'required|totp_code',
        ];
    }

    /**
     * Get the password confirmation validation error messages.
     *
     * @return array
     */
    protected function validationErrorMessages(): array
    {
        return [];
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
