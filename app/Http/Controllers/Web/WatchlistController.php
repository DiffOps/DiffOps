<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Repository;
use App\Models\RepoWatchlist;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WatchlistController extends Controller
{
    /**
     * Display the watchlist.
     */
    public function index(): Response
    {
        $user = auth('supabase')->user();

        $watchlist = RepoWatchlist::with(['repository' => fn ($q) => $q->with('organization')])
            ->where('user_id', $user->id)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->repository->id,
                'name' => $item->repository->name,
                'full_name' => $item->repository->full_name,
                'html_url' => "https://github.com/{$item->repository->full_name}",
                'is_active' => $item->repository->is_active,
                'last_incursion' => $item->repository->pullRequests()
                    ->whereHas('analyses', fn ($q) => $q->where('verdict', '!=', 'pending'))
                    ->with('analyses')
                    ->latest('updated_at')
                    ->first()?->analyses->first() ? [
                        'verdict' => $item->repository->pullRequests()
                            ->whereHas('analyses', fn ($q) => $q->where('verdict', '!=', 'pending'))
                            ->with('analyses')
                            ->latest('updated_at')
                            ->first()->analyses->first()->verdict,
                        'timestamp' => $item->repository->pullRequests()
                            ->whereHas('analyses', fn ($q) => $q->where('verdict', '!=', 'pending'))
                            ->with('analyses')
                            ->latest('updated_at')
                            ->first()->analyses->first()->created_at->toISOString(),
                    ] : null,
            ]);

        return Inertia::render('Watchlist/Index', [
            'watchlist' => $watchlist,
            'realtime' => [
                'channel' => "user:{$user->id}:watchlist",
            ],
        ]);
    }

    /**
     * Toggle repository in watchlist.
     */
    public function toggle(Repository $repository): RedirectResponse
    {
        $user = auth('supabase')->user();

        $existing = RepoWatchlist::where('user_id', $user->id)
            ->where('repository_id', $repository->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Repositório removido da watchlist.');
        }

        RepoWatchlist::create([
            'user_id' => $user->id,
            'repository_id' => $repository->id,
        ]);

        return back()->with('success', 'Repositório adicionado à watchlist.');
    }
}
