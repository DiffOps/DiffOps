<?php

namespace App\Jobs;

use App\Enums\PrState;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Services\GitHub\DiffFetcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class ProcessIncursionJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $payload,
        public ?string $deliveryId = null,
    ) {}

    /**
     * Ingest the pull request event for every organization that registered
     * the repository: normalize the metadata and fetch the diff.
     */
    public function handle(): void
    {
        $repoId = $this->payload['repository']['id'] ?? null;

        if (! is_int($repoId) && ! is_numeric($repoId)) {
            return;
        }

        $repositories = Repository::where('github_repo_id', (int) $repoId)->get();

        if ($repositories->isEmpty()) {
            return;
        }

        $normalized = $this->normalizePayload();

        foreach ($repositories as $repository) {
            $existing = PullRequest::where('organization_id', $repository->organization_id)
                ->where('repo_full_name', $normalized['repo_full_name'])
                ->where('github_pr_number', $normalized['github_pr_number'])
                ->first();

            $previousHeadSha = $existing?->head_sha;

            // Early-return A: closed/merged PRs only get metadata refreshed.
            if ($existing !== null && $normalized['state'] !== PrState::Open) {
                $existing->update($normalized);

                continue;
            }

            // Early-return B: same head sha with stored files is already ingested.
            if ($existing !== null && $previousHeadSha === $normalized['head_sha'] && $existing->files()->exists()) {
                $existing->update($normalized);

                continue;
            }

            $installationId = $this->payload['installation']['id'] ?? null;

            if ($installationId === null) {
                throw new UnexpectedValueException('Webhook payload is missing the installation id.');
            }

            [$owner, $repoName] = explode('/', $normalized['repo_full_name'], 2);

            $files = app(DiffFetcher::class)->files(
                $owner,
                $repoName,
                $normalized['github_pr_number'],
                (int) $installationId,
            );

            DB::transaction(function () use ($repository, $normalized): void {
                PullRequest::updateOrCreate(
                    [
                        'organization_id' => $repository->organization_id,
                        'repo_full_name' => $normalized['repo_full_name'],
                        'github_pr_number' => $normalized['github_pr_number'],
                    ],
                    $normalized,
                );
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(): array
    {
        $pull = $this->payload['pull_request'] ?? [];
        $repo = $this->payload['repository'] ?? [];

        $merged = ($pull['merged'] ?? false) === true || ($pull['merged_at'] ?? null) !== null;
        $state = $merged
            ? PrState::Merged
            : (($pull['state'] ?? null) === 'open' ? PrState::Open : PrState::Closed);

        return [
            'github_repo_id' => (int) $repo['id'],
            'repo_full_name' => (string) $repo['full_name'],
            'github_pr_number' => (int) $this->payload['number'],
            'title' => (string) $pull['title'],
            'author_username' => (string) ($pull['user']['login'] ?? ''),
            'author_avatar_url' => $pull['user']['avatar_url'] ?? null,
            'base_ref' => $pull['base']['ref'] ?? null,
            'head_ref' => $pull['head']['ref'] ?? null,
            'head_sha' => $pull['head']['sha'] ?? null,
            'state' => $state,
            'is_draft' => (bool) ($pull['draft'] ?? false),
            'closed_at' => $pull['closed_at'] ?? null,
        ];
    }
}
