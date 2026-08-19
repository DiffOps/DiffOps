<?php

use App\Services\SupabaseJwksService;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestJwksFixture;
use Tests\Support\TestJwtSigner;

beforeEach(function () {
    config()->set('services.supabase', [
        'url' => TestJwtSigner::ISSUER_BASE,
        'jwt_secret' => TestJwtSigner::SECRET,
        'jwt_audience' => TestJwtSigner::AUDIENCE,
        'jwt_clock_skew' => 30,
        'jwks_url' => TestJwtSigner::ISSUER_BASE.'/auth/v1/.well-known/jwks.json',
        'jwks_cache_ttl' => 3600,
        'jwks_timeout' => 5,
        'last_login_debounce' => 300,
    ]);

    Cache::forget('supabase:jwks');
});

it('fetches and caches the jwks on first access', function () {
    $fixture = TestJwksFixture::make();

    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response($fixture->jwksPayload())]);

    $keys = (new SupabaseJwksService)->keys();

    Http::assertSent(fn ($request) => $request->url() === TestJwtSigner::ISSUER_BASE.'/auth/v1/.well-known/jwks.json');
    expect($keys)->toHaveKey('test-kid-1')
        ->and(Cache::has('supabase:jwks'))->toBeTrue();
});

it('serves the cached keys without touching the network', function () {
    $fixture = TestJwksFixture::make();

    Cache::put('supabase:jwks', JWK::parseKeySet($fixture->jwksPayload(), 'RS256'), 3600);

    Http::fake();

    $keys = (new SupabaseJwksService)->keys();

    Http::assertNothingSent();
    expect($keys)->toHaveKey('test-kid-1');
});

it('refetches the jwks once when the requested kid is missing', function () {
    $alpha = TestJwksFixture::make('alpha-kid');
    $bravo = TestJwksFixture::make('bravo-kid');

    Http::fakeSequence()
        ->push($alpha->jwksPayload())
        ->push($bravo->jwksPayload());

    $key = (new SupabaseJwksService)->keyForKid('bravo-kid');

    Http::assertSentCount(2);
    expect($key)->toBeInstanceOf(Key::class);
});

it('refetches the jwks when the cache ttl has expired', function () {
    $fixture = TestJwksFixture::make();

    Cache::put('supabase:jwks', JWK::parseKeySet($fixture->jwksPayload(), 'RS256'), now()->subMinute());

    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response($fixture->jwksPayload())]);

    $keys = (new SupabaseJwksService)->keys();

    Http::assertSentCount(1);
    expect($keys)->toHaveKey('test-kid-1');
});

it('returns null for a phantom kid after a single refetch', function () {
    $fixture = TestJwksFixture::make();

    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response($fixture->jwksPayload())]);

    $key = (new SupabaseJwksService)->keyForKid('ghost-kid');

    Http::assertSentCount(2);
    expect($key)->toBeNull();
});

it('resolves a single key but not multiple keys when no kid is given', function () {
    $service = new SupabaseJwksService;

    $payload = TestJwksFixture::make()->jwksPayload();

    Http::fake(['*/auth/v1/.well-known/jwks.json' => function ($request) use (&$payload) {
        return Http::response($payload);
    }]);

    expect($service->keyForKid(null))->toBeInstanceOf(Key::class);

    $service->invalidate();

    $payload = [
        'keys' => [
            TestJwksFixture::make('alpha-kid')->jwksPayload()['keys'][0],
            TestJwksFixture::make('bravo-kid')->jwksPayload()['keys'][0],
        ],
    ];

    expect($service->keyForKid(null))->toBeNull();
});

it('does not cache a failed fetch', function () {
    Http::fakeSequence()
        ->push('', 500)
        ->push(TestJwksFixture::make()->jwksPayload());

    $service = new SupabaseJwksService;

    expect(fn () => $service->keys())->toThrow(UnexpectedValueException::class);

    $keys = $service->keys();

    Http::assertSentCount(2);
    expect($keys)->toHaveKey('test-kid-1');
});

it('rejects a jwks payload without keys', function () {
    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response(['foo' => 'bar'])]);

    (new SupabaseJwksService)->keys();
})->throws(UnexpectedValueException::class);

it('applies the configured timeout to the jwks request', function () {
    $capturedTimeout = null;

    Http::fake(['*' => function ($request, array $options) use (&$capturedTimeout) {
        $capturedTimeout = $options['timeout'] ?? null;

        return Http::response(TestJwksFixture::make()->jwksPayload());
    }]);

    (new SupabaseJwksService)->keys();

    expect($capturedTimeout)->toBe(5);
});
