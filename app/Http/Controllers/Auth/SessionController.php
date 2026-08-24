<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SupabaseJwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SessionController extends Controller
{
    private const COOKIE = 'diffops_session';

    public function __construct(private readonly SupabaseJwtService $jwts) {}

    /**
     * Exchange a Supabase access token for the web session cookie.
     */
    public function store(Request $request): Response
    {
        $token = (string) $request->input('token', '');

        try {
            $claims = $this->jwts->decode($token);
        } catch (Throwable) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        // TTL acompanha o access token do Supabase (~1h); renovação é feita
        // pelo supabase-js no cliente, que re-posta o novo token aqui.
        $ttl = max(5, (int) config('services.supabase.session_cookie_ttl', 60));

        // Rotas API não passam por AddQueuedCookiesToResponse: anexar direto.
        $response = response()->noContent();
        $response->headers->setCookie(
            app('cookie')->make(self::COOKIE, $token, $ttl, '/', null, null, true, false, 'Lax'),
        );
        $response->headers->set('X-Diffops-Subject', (string) ($claims['sub'] ?? ''));

        return $response;
    }

    /**
     * Clear the web session cookie (logout).
     */
    public function destroy(): Response
    {
        $response = response()->noContent();
        $response->headers->setCookie(app('cookie')->forget(self::COOKIE));

        return $response;
    }
}
