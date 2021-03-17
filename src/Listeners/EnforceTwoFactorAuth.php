<?php

namespace DarkGhostHunter\Laraguard\Listeners;

use Illuminate\Http\Request;
use Illuminate\Auth\Events\Validated;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Contracts\Config\Repository;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorListener;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;

class EnforceTwoFactorAuth implements TwoFactorListener
{
    use ChecksTwoFactorCode;

    /**
     * Config repository.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * Current Request being handled.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Input name to verify Two Factor Code presence.
     *
     * @var string
     */
    protected $input;

    /**
     * Credentials used for Login in.
     *
     * @var array
     */
    protected $credentials;

    /**
     * If the user should be remembered.
     *
     * @var bool
     */
    protected $remember;

    /**
     * Create a new Subscriber instance.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Http\Request  $request
     */
    public function __construct(Repository $config, Request $request)
    {
        $this->config = $config;
        $this->request = $request;
        $this->input = $config['laraguard.input'];
    }

    /**
     * Saves the credentials temporarily into the class instance.
     *
     * @param  \Illuminate\Auth\Events\Attempting  $event
     * @return void
     */
    public function saveCredentials(Attempting $event)
    {
        $this->credentials = (array) $event->credentials;
        $this->remember = (bool) $event->remember;
    }

    /**
     * Checks if the user should use Two Factor Auth.
     *
     * @param  \Illuminate\Auth\Events\Validated  $event
     * @return void
     */
    public function checkTwoFactor(Validated $event)
    {
        if ($this->shouldUseTwoFactorAuth($event->user)) {
            // If the request doesn't have any code, just throw a response.
            if (! $this->hasCode()) {
                $this->throwResponse($event->user);
            }

            // If the user has set an invalid code, throw him a response.
            if (! $this->hasValidCode($event->user)) {
                $this->throwResponse($event->user, true);
            }

            // The code is valid so we will need to check if the device should
            // be registered as safe. For that, we will check if the config
            // allows it, and there is a checkbox filled to opt-in this.
            if ($this->isSafeDevicesEnabled() && $this->wantsAddSafeDevice()) {
                $event->user->addSafeDevice($this->request);
            }
        }
    }

    /**
     * Creates a response containing the Two Factor Authentication view.
     *
     * @param  \DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable  $user
     * @param  bool  $error
     * @return void
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function throwResponse(TwoFactorAuthenticatable $user, bool $error = false)
    {
        $view = view('laraguard::auth', [
            'action'      => $this->request->fullUrl(),
            'credentials' => $this->credentials,
            'user'        => $user,
            'error'       => $error,
            'remember'    => $this->remember,
            'input'       => $this->input
        ]);

        response($view, $error ? 422 : 403, [
            'Cache-Control' => 'no-cache, must-revalidate',
        ])->throwResponse();
    }
}
