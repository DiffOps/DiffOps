<?php

use App\Services\SupabaseProfileFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestJwtSigner;
use Tests\Support\TestUserProfileFixture;

beforeEach(function (): void {
    config()->set('services.supabase', [
        'url' => TestJwtSigner::ISSUER_BASE,
        'jwt_secret' => TestJwtSigner::SECRET,
        'jwt_audience' => TestJwtSigner::AUDIENCE,
        'jwt_clock_skew' => 30,
        'jwks_url' => TestJwtSigner::ISSUER_BASE.'/auth/v1/.well-known/jwks.json',
        'jwks_cache_ttl' => 3600,
        'jwks_timeout' => 5,
        'last_login_debounce' => 300,
        'profile_sync_http' => true,
        'profile_sync_url' => TestJwtSigner::ISSUER_BASE.'/auth/v1/user',
        'profile_sync_timeout' => 5,
        'profile_sync_cache_ttl' => 300,
    ]);

    Cache::flush();
});

function profileHttpFetcher(): SupabaseProfileFetcher
{
    return new SupabaseProfileFetcher;
}

function profileHttpUrl(): string
{
    return TestJwtSigner::ISSUER_BASE.'/auth/v1/user';
}

it('returns an empty profile without any network when the http sync is disabled', function (): void {
    config()->set('services.supabase.profile_sync_http', false);

    expect(profileHttpFetcher()->fetch('token', TestJwtSigner::SUB))->toBe([]);

    Http::assertNothingSent();
});

it('fetches the profile with the bearer token and normalizes the fields', function (): void {
    Http::fake([profileHttpUrl() => Http::response(TestUserProfileFixture::payload())]);

    $profile = profileHttpFetcher()->fetch('the-access-token', TestJwtSigner::SUB);

    expect($profile)->toBe([
        'name' => 'Operator One',
        'email' => TestUserProfileFixture::EMAIL,
        'github_username' => TestUserProfileFixture::GITHUB_USERNAME,
        'avatar_url' => TestUserProfileFixture::AVATAR,
    ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === profileHttpUrl()
        && $request->hasHeader('Authorization', 'Bearer the-access-token'));
});

it('falls back to the github identity when the top-level fields are missing', function (): void {
    Http::fake([profileHttpUrl() => Http::response(TestUserProfileFixture::payload([
        'email' => null,
        'user_metadata' => [],
    ]))]);

    $profile = profileHttpFetcher()->fetch('token', TestJwtSigner::SUB);

    expect($profile['email'])->toBe(TestUserProfileFixture::EMAIL)
        ->and($profile['name'])->toBe('Operator One')
        ->and($profile['github_username'])->toBe(TestUserProfileFixture::GITHUB_USERNAME)
        ->and($profile['avatar_url'])->toBe(TestUserProfileFixture::AVATAR);
});

it('prefers the top-level email over the github identity email', function (): void {
    Http::fake([profileHttpUrl() => Http::response(TestUserProfileFixture::payload([
        'email' => 'top@diffops.test',
    ]))]);

    $profile = profileHttpFetcher()->fetch('token', TestJwtSigner::SUB);

    expect($profile['email'])->toBe('top@diffops.test');
});

it('falls back to user_metadata when there is no identity', function (): void {
    Http::fake([profileHttpUrl() => Http::response(TestUserProfileFixture::payload([
        'email' => null,
        'identities' => [],
    ]))]);

    $profile = profileHttpFetcher()->fetch('token', TestJwtSigner::SUB);

    expect($profile['name'])->toBe('Operator One')
        ->and($profile['github_username'])->toBe(TestUserProfileFixture::GITHUB_USERNAME)
        ->and($profile['avatar_url'])->toBe(TestUserProfileFixture::AVATAR)
        ->and($profile['email'])->toBeNull();
});

it('caches the profile per token hash so a hot loop does not refetch', function (): void {
    Http::fake([profileHttpUrl() => Http::response(TestUserProfileFixture::payload())]);

    $fetcher = profileHttpFetcher();

    $fetcher->fetch('the-access-token', TestJwtSigner::SUB);
    $fetcher->fetch('the-access-token', TestJwtSigner::SUB);

    Http::assertSentCount(1);

    expect(Cache::has('supabase:profile:'.hash('sha256', 'the-access-token')))->toBeTrue();
});

it('never caches failures so a later success can recover', function (): void {
    Http::fake([
        profileHttpUrl() => Http::sequence()
            ->push('boom', 500)
            ->push(TestUserProfileFixture::payload(), 200),
    ]);

    $fetcher = profileHttpFetcher();

    expect(fn () => $fetcher->fetch('the-access-token', TestJwtSigner::SUB))
        ->toThrow(UnexpectedValueException::class);

    $profile = $fetcher->fetch('the-access-token', TestJwtSigner::SUB);

    expect($profile['email'])->toBe(TestUserProfileFixture::EMAIL);

    Http::assertSentCount(2);

    expect(Cache::has('supabase:profile:'.hash('sha256', 'the-access-token')))->toBeTrue();
});

it('throws when the profile endpoint answers with an error status', function (): void {
    Http::fake([profileHttpUrl() => Http::response('boom', 500)]);

    expect(fn () => profileHttpFetcher()->fetch('token', TestJwtSigner::SUB))
        ->toThrow(UnexpectedValueException::class);
});

it('throws when the profile endpoint rejects the access token', function (): void {
    Http::fake([profileHttpUrl() => Http::response('expired token', 401)]);

    expect(fn () => profileHttpFetcher()->fetch('token', TestJwtSigner::SUB))
        ->toThrow(UnexpectedValueException::class);
});

it('throws when the payload is not an array', function (): void {
    Http::fake([profileHttpUrl() => Http::response('not-json', 200)]);

    expect(fn () => profileHttpFetcher()->fetch('token', TestJwtSigner::SUB))
        ->toThrow(UnexpectedValueException::class);
});

it('throws when the payload subject does not match the token subject', function (): void {
    Http::fake([profileHttpUrl() => Http::response(TestUserProfileFixture::payload([
        'id' => 'another-subject',
    ]))]);

    expect(fn () => profileHttpFetcher()->fetch('token', TestJwtSigner::SUB))
        ->toThrow(UnexpectedValueException::class);
});

it('applies the configured timeout to the profile request', function (): void {
    $capturedTimeout = null;

    Http::fake(['*' => function ($request, array $options) use (&$capturedTimeout) {
        $capturedTimeout = $options['timeout'] ?? null;

        return Http::response(TestUserProfileFixture::payload());
    }]);

    profileHttpFetcher()->fetch('token', TestJwtSigner::SUB);

    expect($capturedTimeout)->toBe(5);
});

it('returns nulls for a minimal payload with only the id', function (): void {
    Http::fake([profileHttpUrl() => Http::response(['id' => TestJwtSigner::SUB])]);

    $profile = profileHttpFetcher()->fetch('token', TestJwtSigner::SUB);

    expect($profile)->toBe([
        'name' => null,
        'email' => null,
        'github_username' => null,
        'avatar_url' => null,
    ]);
});
