<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifySupabaseJwt
{
    /**
     * Authenticate the request against the stateless supabase guard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('supabase')->guest()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
