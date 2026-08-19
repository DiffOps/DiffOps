<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncursionJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubWebhookController extends Controller
{
    /**
     * Entry point for GitHub webhooks (pull_request + ping events).
     *
     * The route is public (no supabase auth) and protected by the HMAC
     * signature middleware, so the body has already been verified.
     */
    public function handle(Request $request): JsonResponse
    {
        $event = (string) $request->header('X-GitHub-Event');
        $delivery = $request->header('X-GitHub-Delivery');

        if ($event === 'ping') {
            return response()->json(['message' => 'pong']);
        }

        $action = (string) $request->input('action');

        if ($event === 'pull_request' && in_array($action, ['opened', 'synchronize', 'reopened', 'closed', 'edited'], true)) {
            ProcessIncursionJob::dispatch($request->all(), $delivery);
        }

        return response()->json([]);
    }
}
