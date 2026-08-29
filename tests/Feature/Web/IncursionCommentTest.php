<?php

declare(strict_types=1);

use App\Jobs\PostReconCommentJob;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\Support\ReconCommentFixtures;
use Tests\Support\TestJwtSigner;

uses(RefreshDatabase::class);

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
    ]);
});

function reconUserForOrg(Organization $org): User
{
    $user = User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'role' => 'operator',
    ]);

    return $user;
}

it('dispatches PostReconCommentJob when the repository allows comments', function (): void {
    $org = Organization::create(['name' => 'Alpha', 'slug' => 'alpha-'.Str::uuid()]);
    reconUserForOrg($org);
    [$repo, $pr, $assessment] = ReconCommentFixtures::scenario($org, ['comment_on_pr' => true]);

    Bus::fake([PostReconCommentJob::class]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->post("/incursions/{$assessment->id}/comment")
        ->assertSessionHas('success');

    Bus::assertDispatched(
        PostReconCommentJob::class,
        fn (PostReconCommentJob $job) => $job->assessment->id === $assessment->id
    );
});

it('does not dispatch and flashes an error when comments are disabled on the repository', function (): void {
    $org = Organization::create(['name' => 'Alpha', 'slug' => 'alpha-'.Str::uuid()]);
    reconUserForOrg($org);
    [$repo, $pr, $assessment] = ReconCommentFixtures::scenario($org, ['comment_on_pr' => false]);

    Bus::fake();

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->post("/incursions/{$assessment->id}/comment")
        ->assertSessionHasErrors('comment');

    Bus::assertNothingDispatched();
});

it('aborts with 403 when the assessment belongs to another organization', function (): void {
    $orgA = Organization::create(['name' => 'Alpha', 'slug' => 'alpha-'.Str::uuid()]);
    $orgB = Organization::create(['name' => 'Bravo', 'slug' => 'bravo-'.Str::uuid()]);
    // User belongs only to org B; the assessment's repository lives in org A.
    reconUserForOrg($orgB);
    [$repo, $pr, $assessment] = ReconCommentFixtures::scenario($orgA, ['comment_on_pr' => true]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->post("/incursions/{$assessment->id}/comment")
        ->assertForbidden();
});
