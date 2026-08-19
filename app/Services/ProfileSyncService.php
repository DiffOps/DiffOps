<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Lazily keeps the local users table in sync with the Supabase profile
 * claims on every successful authentication.
 *
 * Unknown subjects are created on first login; known users are only
 * refreshed when the claims actually change, and last_login_at is debounced
 * so a hot authentication loop does not hammer the database. Operational
 * flags (is_active, is_commander, preferences) are never touched here.
 */
class ProfileSyncService
{
    /**
     * Create the local profile for a subject seen for the first time.
     *
     * @param  array<string, mixed>  $claims
     */
    public function createFromClaims(array $claims): User
    {
        $metadata = (array) ($claims['user_metadata'] ?? []);

        return User::firstOrCreate(
            ['supabase_uid' => $claims['sub']],
            [
                'name' => $metadata['full_name'] ?? $metadata['name'] ?? '',
                'email' => $claims['email'] ?? '',
                'password' => 'diffops-profile-password',
                'github_username' => $metadata['user_name'] ?? $claims['preferred_username'] ?? null,
                'avatar_url' => $metadata['avatar_url'] ?? null,
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
    public function refreshIfChanged(User $user, array $claims): void
    {
        $metadata = (array) ($claims['user_metadata'] ?? []);

        $updates = [];

        $name = $metadata['full_name'] ?? $metadata['name'] ?? null;

        if ($name !== null && (string) $user->name !== (string) $name) {
            $updates['name'] = $name;
        }

        if (isset($claims['email']) && (string) $user->email !== (string) $claims['email']) {
            $updates['email'] = $claims['email'];
        }

        $github = $metadata['user_name'] ?? $claims['preferred_username'] ?? null;

        if ($github !== null && (string) $user->github_username !== (string) $github) {
            $updates['github_username'] = $github;
        }

        $avatar = $metadata['avatar_url'] ?? null;

        if ($avatar !== null && (string) $user->avatar_url !== (string) $avatar) {
            $updates['avatar_url'] = $avatar;
        }

        if ($updates !== []) {
            $user->fill($updates)->save();
        }

        $this->bumpLastLogin($user);
    }

    private function bumpLastLogin(User $user): void
    {
        $key = 'supabase:last-login:'.$user->getKey();

        if (Cache::add($key, now()->timestamp, (int) config('services.supabase.last_login_debounce', 300))) {
            $user->forceFill(['last_login_at' => now()])->save();
        }
    }
}
