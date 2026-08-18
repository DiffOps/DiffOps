<?php

use App\Enums\PrFileStatus;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\PullRequestFile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a uuid primary key on creation', function () {
    $file = createFileRecord();

    expect($file->id)->toBeString()
        ->and(strlen($file->id))->toBe(36);
});

it('accepts the file payload through mass assignment', function () {
    $file = createFileRecord([
        'file_path' => 'src/config/secrets.php',
        'status' => 'added',
        'additions' => 120,
        'deletions' => 0,
        'bytes' => 4096,
        'is_sensitive' => true,
        'is_binary' => false,
        'raw_patch' => "diff --git a/src/config/secrets.php b/src/config/secrets.php\n+ secret",
    ]);

    expect($file->file_path)->toBe('src/config/secrets.php')
        ->and($file->status)->toBeInstanceOf(PrFileStatus::class)
        ->and($file->status)->toBe(PrFileStatus::Added)
        ->and($file->additions)->toBe(120)
        ->and($file->deletions)->toBe(0)
        ->and($file->bytes)->toBe(4096)
        ->and($file->is_sensitive)->toBeTrue()
        ->and($file->is_binary)->toBeFalse()
        ->and($file->raw_patch)->toContain('diff --git');
});

it('casts status, integers and booleans', function () {
    $file = createFileRecord([
        'status' => 'renamed',
        'bytes' => 512,
        'is_sensitive' => false,
        'is_binary' => true,
    ]);

    expect($file->status)->toBe(PrFileStatus::Renamed)
        ->and($file->getRawOriginal('status'))->toBe('renamed')
        ->and($file->additions)->toBeInt()
        ->and($file->deletions)->toBeInt()
        ->and($file->bytes)->toBeInt()
        ->and($file->is_sensitive)->toBeBool()
        ->and($file->is_binary)->toBeTrue();
});

it('belongs to a pull request', function () {
    $file = createFileRecord();

    expect($file->pullRequest)->toBeInstanceOf(PullRequest::class);
});

it('enforces the unique pull request and file path pair', function () {
    $pr = createPullRequestRecordForFile();

    PullRequestFile::create([
        'pull_request_id' => $pr->id,
        'file_path' => 'app/Services/Triage.php',
    ]);

    PullRequestFile::create([
        'pull_request_id' => $pr->id,
        'file_path' => 'app/Services/Triage.php',
    ]);
})->throws(QueryException::class);

function createFileRecord(array $overrides = []): PullRequestFile
{
    return PullRequestFile::create(array_merge([
        'pull_request_id' => createPullRequestRecordForFile()->id,
        'file_path' => 'app/Services/Triage.php',
        'status' => 'modified',
        'additions' => 10,
        'deletions' => 2,
        'bytes' => 1024,
    ], $overrides));
}

function createPullRequestRecordForFile(): PullRequest
{
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    return PullRequest::create([
        'organization_id' => $organization->id,
        'github_repo_id' => 1,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 10,
        'title' => 'Fix login',
        'author_username' => 'devacme',
    ]);
}
