<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestJwtSigner;

uses(RefreshDatabase::class);

it('emits host-relative vite asset urls', function (): void {
    config()->set('services.supabase', [
        'url' => TestJwtSigner::ISSUER_BASE,
        'jwt_secret' => TestJwtSigner::SECRET,
        'jwt_audience' => TestJwtSigner::AUDIENCE,
        'jwt_clock_skew' => 30,
    ]);

    User::create([
        'name' => 'Operator',
        'email' => 'vite-operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $html = $this
        ->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('/build/assets/')
        ->and($html)->not->toMatch('#https?://[^"]+/build/#');
});
