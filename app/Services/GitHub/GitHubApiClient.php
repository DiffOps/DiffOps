<?php

namespace App\Services\GitHub;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GitHubApiClient
{
    public function __construct(private readonly GitHubAppTokenService $tokens) {}

    /**
     * GET a GitHub REST endpoint with retry on 429/5xx.
     *
     * When a Link header with rel="next" is present, $nextUrl receives the
     * absolute URL of the next page so paginated callers can follow it.
     *
     * @return array<mixed>
     */
    public function get(string $path, array $query = [], ?int $installationId = null, ?string &$nextUrl = null): array
    {
        $retries = (int) config('services.github.retries', 2);
        $attempt = 0;
        $nextUrl = null;

        while (true) {
            $response = $this->send($path, $query, $installationId);

            if ($response->successful()) {
                $nextUrl = $this->extractNextLink($response);

                return $response->json() ?? [];
            }

            if (($response->status() === 429 || $response->status() >= 500) && $attempt < $retries) {
                $attempt++;
                usleep(100_000 * $attempt);

                continue;
            }

            $response->throw();
        }
    }

    private function send(string $path, array $query, ?int $installationId): Response
    {
        $request = Http::timeout((int) config('services.github.timeout', 15))
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'DiffOps',
            ]);

        if ($installationId !== null) {
            $request = $request->withToken($this->tokens->tokenForInstallation($installationId));
        }

        $url = str_starts_with($path, 'http')
            ? $path
            : rtrim((string) config('services.github.api_url', 'https://api.github.com'), '/').$path;

        return $request->get($url, $query);
    }

    private function extractNextLink(Response $response): ?string
    {
        $link = $response->header('Link');

        if (! is_string($link)) {
            return null;
        }

        if (preg_match('/<([^>]+)>;\s*rel="next"/', $link, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
