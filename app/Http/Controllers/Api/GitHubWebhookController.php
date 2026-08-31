<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeIncursionJob;
use App\Jobs\ProcessIncursionJob;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        try {
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
            Log::info('Audit log created successfully', ['action' => $action, 'event' => $event]);
        } catch (\Throwable $e) {
            Log::error('AuditLogService error: ' . $e->getMessage());
        }

        Log::info('Event processed', ['event' => $event, 'action' => $action]);

        if ($event === 'ping') {
            return response()->json(['message' => 'pong']);
        }

        if ($event === 'pull_request' && in_array($action, ['opened', 'synchronize', 'reopened', 'closed', 'edited'], true)) {
            Log::info('Dispatching ProcessIncursionJob', ['action' => $action, 'event' => $event]);
            ProcessIncursionJob::dispatch($request->all(), $delivery);
            Log::info('ProcessIncursionJob dispatched');
        }

        if ($event === 'pull_request' && in_array($action, ['opened', 'synchronize', 'reopened'], true)) {
            Log::info('Dispatching AnalyzeIncursionJob', ['action' => $action, 'event' => $event]);
            AnalyzeIncursionJob::dispatch($request->all(), $delivery);
            Log::info('AnalyzeIncursionJob dispatched');
        }

        return response()->json([]);
    }
}
