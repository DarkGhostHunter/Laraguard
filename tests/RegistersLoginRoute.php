<?php

namespace Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

trait RegistersLoginRoute
{
    protected function registerLoginRoute()
    {
        Route::post('login', function (Request $request) {
            return Auth::guard('web')->attempt($request->only('email', 'password'), $request->filled('remember'))
                ? 'authenticated'
                : 'unauthenticated';
        })->middleware('web');
    }
}
