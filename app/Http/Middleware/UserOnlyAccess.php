<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class UserOnlyAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Check if user is logged in and is an instance of your User model
        if (!$user || get_class($user) !== \App\Models\User::class) {
            abort(403, 'Access denied.');
        }

        // Optional: Check for role or permission (Spatie)
        // if (!$user->hasRole('user') || !$user->can('access dashboard')) {
        //     abort(403, 'You do not have permission to view the dashboard.');
        // }

        return $next($request);
    }
}
