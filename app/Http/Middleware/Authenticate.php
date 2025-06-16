<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as MiddlewareAuthenticate;

class Authenticate extends MiddlewareAuthenticate
{
    protected function unauthenticated($request, array $guards)
    {

        foreach ($guards as $guard) {
            switch ($guard) {
                case 'admin':
                    throw new AuthenticationException(redirectTo: route('admin.login'));
                    break;
                default:
                    throw new AuthenticationException(redirectTo: route('login'));
            }
        }
    }
}
