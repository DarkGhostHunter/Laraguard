<?php

namespace DarkGhostHunter\Laraguard\Listeners;

use Illuminate\Http\Request;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Contracts\Config\Repository;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;

class ForcesTwoFactorAuth
{
    /**
     * Authentication Guard manager.
     *
     * @var \Illuminate\Auth\AuthManager
     */
    protected $auth;

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
     * Create a new ForcesTwoFactorAuth instance.
     *
     * @param  \Illuminate\Auth\AuthManager  $auth
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Http\Request  $request
     */
    public function __construct(AuthManager $auth, Repository $config, Request $request)
    {
        $this->auth = $auth;
        $this->config = $config;
        $this->request = $request;
        $this->input = $config->get('laraguard.input');
    }

    /**
     * Handle the event.
     *
     * @param  \Illuminate\Auth\Events\Attempting|\Illuminate\Auth\Events\Validated  $event
     * @return void
     */
    public function handle($event)
    {
        $user = $this->retrieveUser($event);

        if ($this->shouldUseTwoFactorAuth($user)) {

            if ($this->isSafeDevice($user) || ($this->hasCode() && $invalid = $this->hasValidCode($user))) {
                return $this->addSafeDevice($user);
            }

            $this->throwResponse($event, $user, isset($invalid));
        }
    }

    /**
     * Retrieve the User from the event.
     *
     * @param  \Illuminate\Auth\Events\Attempting|\Illuminate\Auth\Events\Validated  $event
     * @return null|\DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable|\Illuminate\Contracts\Auth\Authenticatable
     */
    protected function retrieveUser($event)
    {
        return $event instanceof Attempting
            ? $this->getUserFromProvider($event->guard, $event->credentials)
            : $event->user;
    }

    /**
     * Returns the User from the User Provider used by the Guard.
     *
     * @param  string  $guard
     * @param  array  $credentials
     * @return null|\DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable|\Illuminate\Contracts\Auth\Authenticatable
     */
    protected function getUserFromProvider(string $guard, array $credentials = [])
    {
        $provider = $this->auth->createUserProvider($this->getProviderFromGuard($guard));

        $user = $this->auth
            ->createUserProvider($this->getProviderFromGuard($guard))
            ->retrieveByCredentials($credentials);

        return $user && $provider->validateCredentials($user, $credentials) ? $user : null;
    }

    /**
     * Returns if the login attempt should enforce Two Factor Authentication.
     *
     * @param  null|\DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable|\Illuminate\Contracts\Auth\Authenticatable  $user
     * @return bool
     */
    protected function shouldUseTwoFactorAuth($user = null)
    {
        if (! $user instanceof TwoFactorAuthenticatable) {
            return false;
        }

        $shouldUse = $user->hasTwoFactorEnabled();

        if ($this->config['laraguard.safe_devices.enabled']) {
            return $shouldUse && ! $user->isSafeDevice($this->request);
        }

        return $shouldUse;
    }

    /**
     * Returns the provider being used for the guard.
     *
     * @param  string  $guard
     * @return mixed
     */
    protected function getProviderFromGuard(string $guard)
    {
        return $this->config["auth.guards.$guard.provider"];
    }

    /**
     * Returns if the Request is from a Safe Device.
     *
     * @param  \DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable  $user
     * @return bool
     */
    protected function isSafeDevice(TwoFactorAuthenticatable $user)
    {
        return $this->config['laraguard.safe_devices.enabled'] && $user->isSafeDevice($this->request);
    }

    /**
     * Returns if the Request has the Two Factor Code.
     *
     * @return bool
     */
    protected function hasCode()
    {
        return $this->request->has($this->input);
    }

    /**
     * Checks if the Request has a Two Factor Code and is correct (even if is invalid).
     *
     * @param  \DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable  $user
     * @return bool
     */
    protected function hasValidCode(TwoFactorAuthenticatable $user)
    {
        return ! validator($this->request->only($this->input), [$this->input => 'alphanum'])->fails()
            && $user->validateTwoFactorCode($this->request->input($this->input));
    }

    /**
     * Adds a safe device to Two Factor Authentication data.
     *
     * @param  \DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable  $user
     * @return void
     */
    protected function addSafeDevice(TwoFactorAuthenticatable $user)
    {
        if ($this->config['laraguard.safe_devices.enabled']) {
            $user->addSafeDevice($this->request);
        }
    }

    /**
     * Creates a response containing the Two Factor Authentication view.
     *
     * @param  \Illuminate\Auth\Events\Attempting  $event
     * @param  \DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable  $user
     * @param  bool  $error
     * @return void
     */
    protected function throwResponse(Attempting $event, TwoFactorAuthenticatable $user, bool $error = false)
    {
        $view = view('laraguard::auth', [
            'action'      => $this->request->fullUrl(),
            'credentials' => $event->credentials,
            'user'        => $user,
            'error'       => $error,
            'remember'    => $event->remember,
        ]);

        return response($view, $error ? 422 : 403)->throwResponse();
    }

}
