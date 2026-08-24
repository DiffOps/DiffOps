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
     *
     * Browsers cannot send Authorization headers on document navigations, so
     * web sessions fall back to the encrypted session cookie written by
     * POST /auth/session. The bearer token always wins when present.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() && $request->hasCookie($cookie = config('services.supabase.session_cookie', 'diffops_session'))) {
            $request->headers->set('Authorization', 'Bearer '.$request->cookie($cookie));
        }

        if (Auth::guard('supabase')->guest()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
