<?php

namespace Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Exceptions\HttpResponseException;

trait RegistersLoginRoute
{
    protected function registerLoginRoute(): void
    {
        Route::post('login', function (Request $request) {
            try {
                return Auth::guard('web')->attempt($request->only('email', 'password'), $request->filled('remember'))
                    ? 'authenticated'
                    : 'unauthenticated';
            } catch (\Throwable $exception) {
                if (! $exception instanceof HttpResponseException) {
                    var_dump([get_class($exception), $exception->getMessage()]);
                }
                throw $exception;
            }
        })->middleware('web');
    }
}
