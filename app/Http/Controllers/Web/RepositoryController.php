<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreRepositoryRequest;
use App\Http\Requests\Web\UpdateRepositoryRequest;
use App\Models\Repository;
use App\Services\GitHub\GitHubApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    public function __construct(
        private readonly GitHubApiClient $githubClient
    ) {}

    /**
     * Display a listing of repositories.
     */
    public function index(): Response
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            // User without an active organization: show the empty state.
            return Inertia::render('Repositories/Index', [
                'repositories' => new LengthAwarePaginator([], 0, 15),
                'webhookUrl' => url('/api/webhooks/github'),
            ]);
        }

        $repositories = Repository::where('organization_id', $organization->id)
            ->latest()
            ->paginate(15)
            ->through(fn ($repo) => [
                'id' => $repo->id,
                'name' => $repo->name,
                'full_name' => $repo->full_name,
                'owner_login' => $repo->owner_login,
                'html_url' => "https://github.com/{$repo->full_name}",
                'is_active' => $repo->is_active,
                'comment_on_pr' => $repo->comment_on_pr,
                'escalate_on_hostile' => $repo->escalate_on_hostile,
                'escalation_webhook_url' => $repo->escalation_webhook_url,
                'security_level' => $repo->security_level,
                'webhook_status' => $repo->webhook_active ? 'connected' : 'pending',
                'last_incursion_at' => $repo->pullRequests()
                    ->whereHas('analyses', fn ($q) => $q->where('verdict', '!=', 'pending'))
                    ->latest('updated_at')
                    ->first()?->updated_at?->toISOString(),
            ]);

        return Inertia::render('Repositories/Index', [
            'repositories' => $repositories,
            'webhookUrl' => url('/api/webhooks/github'),
        ]);
    }

    /**
     * Store a newly created repository (register from GitHub).
     */
    public function store(StoreRepositoryRequest $request): RedirectResponse
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            return back()->withErrors([
                'organization_id' => 'Nenhuma organização ativa para registrar o repositório.',
            ]);
        }

        $githubRepoId = $request->validated()['github_repo_id'];
        $installationId = $request->validated()['installation_id'] ?? null;

        // Fetch repo details from GitHub
        try {
            $repoData = $this->githubClient->get("/repositories/{$githubRepoId}", [], $installationId);
        } catch (\Exception $e) {
            return back()->withErrors(['github_repo_id' => 'Repositório não encontrado ou sem acesso.']);
        }

        $repository = Repository::updateOrCreate(
            ['github_repo_id' => $githubRepoId, 'organization_id' => $organization->id],
            [
                'name' => $repoData['name'],
                'full_name' => $repoData['full_name'],
                'owner_login' => $repoData['owner']['login'],
                'owner_avatar_url' => $repoData['owner']['avatar_url'] ?? null,
                'is_active' => true,
                'comment_on_pr' => false,
                'escalate_on_hostile' => false,
                'security_level' => 'standard',
                'installation_id' => $installationId,
            ]
        );

        // TODO: Create webhook via GitHub App (requires installation token)

        return redirect()->route('repos.index')
            ->with('success', "Repositório {$repository->full_name} registrado com sucesso.");
    }

    /**
     * Display the specified repository.
     */
    public function show(Repository $repository): Response
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            abort(403);
        }

        if ($repository->organization_id !== $organization->id) {
            abort(403);
        }

        $repository->load('pullRequests.analyses');

        $recentIncursions = $repository->pullRequests()
            ->whereHas('analyses', fn ($q) => $q->where('verdict', '!=', 'pending'))
            ->with('analyses')
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($pr) => [
                'id' => $pr->id,
                'number' => $pr->github_pr_number,
                'title' => $pr->title,
                'state' => $pr->state,
                'last_analysis' => $pr->analyses->first() ? [
                    'verdict' => $pr->analyses->first()->verdict,
                    'threat_score' => $pr->analyses->first()->security_score,
                    'defcon_level' => $pr->analyses->first()->defcon_level,
                    'created_at' => $pr->analyses->first()->created_at->toISOString(),
                ] : null,
            ]);

        return Inertia::render('Repositories/Show', [
            'repository' => [
                'id' => $repository->id,
                'name' => $repository->name,
                'full_name' => $repository->full_name,
                'owner_login' => $repository->owner_login,
                'html_url' => "https://github.com/{$repository->full_name}",
                'is_active' => $repository->is_active,
                'comment_on_pr' => $repository->comment_on_pr,
                'escalate_on_hostile' => $repository->escalate_on_hostile,
                'escalation_webhook_url' => $repository->escalation_webhook_url,
                'security_level' => $repository->security_level,
                'webhook_active' => $repository->webhook_active,
            ],
            'recentIncursions' => $recentIncursions,
        ]);
    }

    /**
     * Update the specified repository.
     */
    public function update(UpdateRepositoryRequest $request, Repository $repository): RedirectResponse
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            abort(403);
        }

        if ($repository->organization_id !== $organization->id) {
            abort(403);
        }

        $repository->update($request->validated());

        return back()->with('success', 'Configurações atualizadas.');
    }

    /**
     * Remove the specified repository.
     */
    public function destroy(Repository $repository): RedirectResponse
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            abort(403);
        }

        if ($repository->organization_id !== $organization->id) {
            abort(403);
        }

        $repository->delete();

        return redirect()->route('repos.index')
            ->with('success', 'Repositório removido.');
    }
}
