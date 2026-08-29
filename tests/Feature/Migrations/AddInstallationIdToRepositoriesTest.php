<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('adds a nullable installation_id column with an index to repositories', function (): void {
    expect(Schema::hasColumn('repositories', 'installation_id'))->toBeTrue();

    // Index name follows Laravel's auto-naming convention for ->index().
    expect(Schema::hasIndex('repositories', 'repositories_installation_id_index'))->toBeTrue();
});

it('round-trips installation_id through the Repository model fillable and cast', function (): void {
    $org = Organization::create([
        'name' => 'Recon Org',
        'slug' => 'recon-'.Str::uuid(),
    ]);

    $repository = Repository::create([
        'organization_id' => $org->id,
        'github_repo_id' => 123456,
        'full_name' => 'recon-org/web',
        'installation_id' => 99887766,
    ]);

    expect($repository->installation_id)->toBe(99887766)
        ->and(Repository::find($repository->id)->installation_id)->toBe(99887766);
});
