<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsLogController extends Controller
{
    /**
     * Display a listing of combat history (audit logs).
     */
    public function index(Request $request): Response
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            // User without an active organization: show the empty state.
            return Inertia::render('OperationsLog/Index', [
                'logs' => [],
                'filters' => [
                    'actions' => [],
                    'entityTypes' => [],
                ],
            ]);
        }

        $query = AuditLog::with('user')
            ->whereHas('user', fn ($q) => $q->where('organization_id', $organization->id))
            ->latest('created_at');

        // Filters
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('action', 'like', "%{$request->search}%")
                    ->orWhere('entity_type', 'like', "%{$request->search}%");
            });
        }

        $logs = $query->paginate(50)->through(fn ($log) => [
            'id' => $log->id,
            'timestamp' => $log->created_at->toISOString(),
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'user' => $log->user ? [
                'username' => $log->user->username,
                'avatar_url' => $log->user->avatar_url,
            ] : null,
            'payload' => $log->payload,
        ]);

        // Get filter options
        $actions = AuditLog::whereHas('user', fn ($q) => $q->where('organization_id', $organization->id))
            ->distinct('action')
            ->pluck('action');
        $entityTypes = AuditLog::whereHas('user', fn ($q) => $q->where('organization_id', $organization->id))
            ->distinct('entity_type')
            ->pluck('entity_type');

        return Inertia::render('OperationsLog/Index', [
            'logs' => $logs,
            'filters' => [
                'actions' => $actions,
                'entityTypes' => $entityTypes,
            ],
        ]);
    }

    /**
     * Export combat history as CSV.
     */
    public function export(Request $request)
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            // No active organization: stream an empty combat-history CSV.
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="combat-history-'.now()->format('Y-m-d').'.csv"',
            ];

            $callback = function () {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Timestamp', 'Action', 'Entity Type', 'Entity ID', 'User', 'Payload']);
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        $query = AuditLog::with('user')
            ->whereHas('user', fn ($q) => $q->where('organization_id', $organization->id))
            ->latest('created_at');

        // Apply same filters
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="combat-history-'.now()->format('Y-m-d').'.csv"',
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Timestamp', 'Action', 'Entity Type', 'Entity ID', 'User', 'Payload']);
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->toISOString(),
                    $log->action,
                    $log->entity_type,
                    $log->entity_id,
                    $log->user?->username ?? 'system',
                    json_encode($log->payload),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
