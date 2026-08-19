<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateGitHubSignature
{
    /**
     * Verify the X-Hub-Signature-256 HMAC of the raw webhook body.
     *
     * Fails closed: a missing secret, a missing/malformed header or a
     * non-matching signature all answer 401.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.github.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (! is_string($signature) || ! str_starts_with($signature, 'sha256=')) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        $provided = substr($signature, strlen('sha256='));

        if (! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
