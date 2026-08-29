<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\TestJwtSigner;

uses(RefreshDatabase::class);

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
});

function memberUser(array $orgs): User
{
    $user = User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    foreach ($orgs as $org) {
        $user->organizations()->attach($org->id, ['id' => Str::uuid()->toString()]);
    }

    return $user;
}

it('falls back to the first membership when no org cookie is present', function () {
    $alpha = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
    $bravo = Organization::create(['name' => 'Bravo', 'slug' => 'bravo']);
    memberUser([$alpha, $bravo]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentOrganization', [
            'id' => $alpha->id,
            'name' => 'Alpha',
            'slug' => 'alpha',
        ]));
});

it('honors the diffops_org cookie when it points to a valid membership', function () {
    $alpha = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
    $bravo = Organization::create(['name' => 'Bravo', 'slug' => 'bravo']);
    memberUser([$alpha, $bravo]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->withUnencryptedCookie('diffops_org', $bravo->id)
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentOrganization', [
            'id' => $bravo->id,
            'name' => 'Bravo',
            'slug' => 'bravo',
        ]));
});

it('writes the diffops_org cookie on a valid switch', function () {
    $alpha = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
    $bravo = Organization::create(['name' => 'Bravo', 'slug' => 'bravo']);
    memberUser([$alpha, $bravo]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->from('/dashboard')
        ->post('/org/switch', ['organization_id' => $bravo->id])
        ->assertRedirect()
        ->assertCookie('diffops_org', (string) $bravo->id, false);
});

it('rejects a switch to an organization the user is not a member of', function () {
    $alpha = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
    $bravo = Organization::create(['name' => 'Bravo', 'slug' => 'bravo']);
    memberUser([$alpha]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->from('/dashboard')
        ->post('/org/switch', ['organization_id' => $bravo->id])
        ->assertRedirect()
        ->assertSessionHasErrors('organization_id')
        ->assertCookieMissing('diffops_org');
});
