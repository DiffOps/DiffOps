<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function attachOrg(User $user, Organization $org): void
{
    $user->organizations()->attach($org->id, ['id' => Str::uuid()->toString()]);
}

it('returns the first membership as the current organization by default', function () {
    $user = User::factory()->create();
    $alpha = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
    $bravo = Organization::create(['name' => 'Bravo', 'slug' => 'bravo']);
    attachOrg($user, $alpha);
    attachOrg($user, $bravo);

    expect($user->currentOrganization)->not->toBeNull();
    expect($user->currentOrganization->id)->toBe($alpha->id);
});

it('returns the explicit override when set via setCurrentOrganization', function () {
    $user = User::factory()->create();
    $alpha = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
    $bravo = Organization::create(['name' => 'Bravo', 'slug' => 'bravo']);
    attachOrg($user, $alpha);
    attachOrg($user, $bravo);

    $user->setCurrentOrganization($bravo);

    expect($user->currentOrganization->id)->toBe($bravo->id);
});

it('returns null when the user has no memberships', function () {
    $user = User::factory()->create();

    expect($user->currentOrganization)->toBeNull();
});
