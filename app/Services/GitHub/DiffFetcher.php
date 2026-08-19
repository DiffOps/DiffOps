<?php

namespace App\Services\GitHub;

class DiffFetcher
{
    private const MAX_PAGES = 30;

    public function __construct(private readonly GitHubApiClient $client) {}

    /**
     * Fetch all files changed by a pull request, following the Link
     * rel="next" header with a hard page cap.
     *
     * @return array<int, array<string, mixed>>
     */
    public function files(string $owner, string $repo, int $number, int $installationId): array
    {
        $all = [];
        $path = "/repos/{$owner}/{$repo}/pulls/{$number}/files";
        $pages = 0;

        do {
            $nextUrl = null;
            $page = $this->client->get($path, ['per_page' => 100], $installationId, $nextUrl);
            $all = array_merge($all, $page);
            $path = $nextUrl;
            $pages++;
        } while ($nextUrl !== null && $pages < self::MAX_PAGES);

        return $all;
    }

    /**
     * Fetch the pull request metadata.
     *
     * @return array<string, mixed>
     */
    public function pull(string $owner, string $repo, int $number, int $installationId): array
    {
        return $this->client->get("/repos/{$owner}/{$repo}/pulls/{$number}", [], $installationId);
    }
}
