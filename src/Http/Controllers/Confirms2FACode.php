<?php

namespace DarkGhostHunter\Laraguard\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\RedirectsUsers;

trait Confirms2FACode
{
    use RedirectsUsers;

    /**
     * Display the password confirmation view.
     *
     * @return \Illuminate\View\View
     */
    public function showConfirmForm()
    {
        return view('laraguard::confirm');
    }

    /**
     * Confirm the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function confirm(Request $request)
    {
        $request->validate($this->rules(), $this->validationErrorMessages());

        $this->resetTotpConfirmationTimeout($request);

        return $request->wantsJson() ? response()->noContent() : redirect()->intended($this->redirectPath());
    }

    /**
     * Reset the password confirmation timeout.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function resetTotpConfirmationTimeout(Request $request)
    {
        $request->session()->put('2fa.totp_confirmed_at', now()->timestamp);
    }

    /**
     * Get the password confirmation validation rules.
     *
     * @return array
     */
    protected function rules()
    {
        return [
            config('laraguard.input') => 'required|numeric',
        ];
    }

    /**
     * Get the password confirmation validation error messages.
     *
     * @return array
     */
    protected function validationErrorMessages()
    {
        return [];
    }
}