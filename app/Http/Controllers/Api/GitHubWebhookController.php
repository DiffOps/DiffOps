<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeIncursionJob;
use App\Jobs\ProcessIncursionJob;
use App\Services\AuditLogService;
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
    public function handle(Request $request, AuditLogService $audit): JsonResponse
    {
        $event = (string) $request->header('X-GitHub-Event');
        $delivery = $request->header('X-GitHub-Delivery');
        $action = (string) $request->input('action');

        // Combat History: record the incoming event for traceability.
        // We never persist the raw body — only safe metadata.
        $audit->log(
            action: $event === 'ping' ? 'webhook.ping' : 'webhook.received',
            entityType: 'pull_request',
            userId: null,
            entityId: null,
            payload: [
                'event' => $event,
                'action' => $action !== '' ? $action : null,
                'delivery_id' => $delivery,
                'repo_full_name' => $request->input('repository.full_name'),
                'pr_number' => $request->input('number'),
            ],
        );

        if ($event === 'ping') {
            return response()->json(['message' => 'pong']);
        }

        if ($event === 'pull_request' && in_array($action, ['opened', 'synchronize', 'reopened', 'closed', 'edited'], true)) {
            ProcessIncursionJob::dispatch($request->all(), $delivery);
        }

        if ($event === 'pull_request' && in_array($action, ['opened', 'synchronize', 'reopened'], true)) {
            AnalyzeIncursionJob::dispatch($request->all(), $delivery);
        }

        return response()->json([]);
    }
}
