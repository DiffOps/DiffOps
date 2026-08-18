<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps the scaffold integer id (no uuid)', function () {
    $user = createUserRecord();

    expect($user->id)->toBeInt()
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('accepts the tactical profile through mass assignment', function () {
    $user = User::create([
        'name' => 'Dev',
        'email' => 'dev@acme.test',
        'password' => bcrypt('secret'),
        'supabase_uid' => '8f14e45f-ceea-4a3e-9b7c-1a2b3c4d5e6f',
        'github_username' => 'devacme',
        'avatar_url' => 'https://avatars.example.com/dev.png',
        'is_commander' => true,
        'preferences' => ['theme' => 'tactical', 'notifications' => true],
        'last_login_at' => '2026-08-18 10:00:00',
    ]);

    expect($user->supabase_uid)->toBe('8f14e45f-ceea-4a3e-9b7c-1a2b3c4d5e6f')
        ->and($user->github_username)->toBe('devacme')
        ->and($user->avatar_url)->toBe('https://avatars.example.com/dev.png')
        ->and($user->is_commander)->toBeTrue()
        ->and($user->preferences)->toBe(['theme' => 'tactical', 'notifications' => true]);
});

it('casts is_commander, preferences and last_login_at', function () {
    $user = User::create([
        'name' => 'Dev',
        'email' => 'dev@acme.test',
        'password' => bcrypt('secret'),
        'is_commander' => true,
        'preferences' => ['alerts' => true],
        'last_login_at' => '2026-08-18 10:00:00',
    ]);

    expect($user->is_commander)->toBeTrue()
        ->and($user->preferences)->toBeArray()
        ->and($user->preferences)->toBe(['alerts' => true])
        ->and($user->last_login_at)->toBeInstanceOf(Carbon::class)
        ->and((int) DB::table('users')->where('id', $user->id)->value('is_commander'))->toBe(1);
});

it('exposes memberships and organizations through the pivot', function () {
    $user = createUserRecord();
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::Commander->value,
    ]);

    expect($user->memberships)->toHaveCount(1)
        ->and($user->memberships->first())->toBeInstanceOf(OrganizationMember::class)
        ->and($user->organizations)->toHaveCount(1)
        ->and($user->organizations->first()->id)->toBe($organization->id);
});

it('keeps factory and notifiable traits available', function () {
    expect(in_array(HasFactory::class, class_uses_recursive(User::class)))->toBeTrue()
        ->and(in_array(Notifiable::class, class_uses_recursive(User::class)))->toBeTrue()
        ->and(User::factory()->count(2)->create())->toHaveCount(2);
});

function createUserRecord(): User
{
    return User::create([
        'name' => 'Dev',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('secret'),
    ]);
}
