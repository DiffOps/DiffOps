<?php

namespace App\Auth;

use App\Models\User;
use App\Services\SupabaseJwtService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use UnexpectedValueException;

/**
 * Stateless guard that authenticates a Supabase access token (HS256).
 *
 * The Bearer token is decoded by the SupabaseJwtService; the subject is then
 * matched against the local users table. Inactive or unknown users are never
 * authenticated, and any decoding failure degrades to a guest.
 */
class SupabaseJwtGuard implements Guard
{
    private ?Authenticatable $user = null;

    private bool $resolved = false;

    public function __construct(
        private readonly ?UserProvider $provider,
        private readonly Request $request,
        private readonly array $config = [],
    ) {}

    public function user(): ?Authenticatable
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;

        $token = $this->bearerToken();

        if ($token === null) {
            return null;
        }

        try {
            $claims = app(SupabaseJwtService::class)->decode($token);
        } catch (UnexpectedValueException) {
            return null;
        }

        $user = $this->provider?->retrieveByCredentials(['supabase_uid' => $claims['sub']]);

        if (! $user instanceof User || $user->is_active === false) {
            return null;
        }

        return $this->user = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function id()
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;
        $this->resolved = true;

        return $this;
    }

    private function bearerToken(): ?string
    {
        $header = $this->request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }
}
