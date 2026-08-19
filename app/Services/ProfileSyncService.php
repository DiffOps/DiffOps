<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Lazily keeps the local users table in sync with the Supabase profile
 * claims on every successful authentication.
 *
 * Unknown subjects are created on first login; known users are only
 * refreshed when the claims actually change, and last_login_at is debounced
 * so a hot authentication loop does not hammer the database. Operational
 * flags (is_active, is_commander, preferences) are never touched here.
 *
 * When an access token is available and the HTTP profile sync is enabled,
 * the GoTrue profile fetched by the SupabaseProfileFetcher fills only the
 * gaps left by the claims (present claims always win). A failing fetch is
 * reported and degrades to the claims-only behavior — auth never breaks
 * because of the profile enrichment.
 */
class ProfileSyncService
{
    private ?SupabaseProfileFetcher $fetcher = null;

    public function __construct(?SupabaseProfileFetcher $fetcher = null)
    {
        $this->fetcher = $fetcher;
    }

    /**
     * Create the local profile for a subject seen for the first time.
     *
     * @param  array<string, mixed>  $claims
     */
    public function createFromClaims(array $claims, ?string $accessToken = null): User
    {
        $profile = $this->mergedProfile($claims, $accessToken);

        return User::firstOrCreate(
            ['supabase_uid' => $claims['sub']],
            [
                'name' => $profile['name'] ?? '',
                'email' => $profile['email'] ?? '',
                'password' => 'diffops-profile-password',
                'github_username' => $profile['github_username'] ?? null,
                'avatar_url' => $profile['avatar_url'] ?? null,
                'last_login_at' => now(),
            ],
        );
    }

    /**
     * Refresh the profile attributes when the claims differ from the model.
     *
     * Absent claims are treated as "keep the current value", so a token
     * without profile data never erases the stored profile.
     *
     * @param  array<string, mixed>  $claims
     */
    public function refreshIfChanged(User $user, array $claims, ?string $accessToken = null): void
    {
        $profile = $this->mergedProfile($claims, $accessToken);

        $updates = [];

        if ($profile['name'] !== null && (string) $user->name !== (string) $profile['name']) {
            $updates['name'] = $profile['name'];
        }

        if ($profile['email'] !== null && (string) $user->email !== (string) $profile['email']) {
            $updates['email'] = $profile['email'];
        }

        if ($profile['github_username'] !== null && (string) $user->github_username !== (string) $profile['github_username']) {
            $updates['github_username'] = $profile['github_username'];
        }

        if ($profile['avatar_url'] !== null && (string) $user->avatar_url !== (string) $profile['avatar_url']) {
            $updates['avatar_url'] = $profile['avatar_url'];
        }

        if ($updates !== []) {
            $user->fill($updates)->save();
        }

        $this->bumpLastLogin($user);
    }

    /**
     * Merge the claims with the fetched profile: a present claim (non-null,
     * non-empty string) always wins; the HTTP profile fills the gaps.
     *
     * @param  array<string, mixed>  $claims
     * @return array{name: string|null, email: string|null, github_username: string|null, avatar_url: string|null}
     */
    private function mergedProfile(array $claims, ?string $accessToken): array
    {
        $metadata = (array) ($claims['user_metadata'] ?? []);
        $httpProfile = $this->fetchProfile($accessToken, (string) ($claims['sub'] ?? ''));

        $name = $metadata['full_name'] ?? $metadata['name'] ?? null;
        $email = $claims['email'] ?? null;
        $github = $metadata['user_name'] ?? $claims['preferred_username'] ?? null;
        $avatar = $metadata['avatar_url'] ?? null;

        return [
            'name' => $this->present($name) ? $name : ($httpProfile['name'] ?? null),
            'email' => $this->present($email) ? $email : ($httpProfile['email'] ?? null),
            'github_username' => $this->present($github) ? $github : ($httpProfile['github_username'] ?? null),
            'avatar_url' => $this->present($avatar) ? $avatar : ($httpProfile['avatar_url'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchProfile(?string $accessToken, string $sub): array
    {
        if ($accessToken === null) {
            return [];
        }

        try {
            return $this->fetcher()->fetch($accessToken, $sub);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Whether a claim value is "present" and therefore wins over the fetch.
     */
    private function present(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        return ! is_string($value) || $value !== '';
    }

    private function fetcher(): SupabaseProfileFetcher
    {
        return $this->fetcher ??= app(SupabaseProfileFetcher::class);
    }

    private function bumpLastLogin(User $user): void
    {
        $key = 'supabase:last-login:'.$user->getKey();

        if (Cache::add($key, now()->timestamp, (int) config('services.supabase.last_login_debounce', 300))) {
            $user->forceFill(['last_login_at' => now()])->save();
        }
    }
}
