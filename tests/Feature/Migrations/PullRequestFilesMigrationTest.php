<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates pull_request_files with the expected columns', function () {
    expect(Schema::hasTable('pull_request_files'))->toBeTrue();

    $columns = array_column(Schema::getColumns('pull_request_files'), 'name');

    expect($columns)->toContain(
        'id', 'pull_request_id', 'file_path', 'status', 'additions', 'deletions',
        'bytes', 'is_sensitive', 'is_binary', 'raw_patch', 'created_at', 'updated_at'
    );
});

it('defaults counters to zero and flags to false', function () {
    $prId = seedPullRequest();
    $fileId = (string) Str::uuid();

    DB::table('pull_request_files')->insert([
        'id' => $fileId,
        'pull_request_id' => $prId,
        'file_path' => 'app/Http/Controllers/HomeController.php',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $file = DB::table('pull_request_files')->where('id', $fileId)->first();

    expect((int) $file->additions)->toBe(0)
        ->and((int) $file->deletions)->toBe(0)
        ->and((int) $file->bytes)->toBe(0)
        ->and((bool) $file->is_sensitive)->toBeFalse()
        ->and((bool) $file->is_binary)->toBeFalse()
        ->and($file->status)->toBeNull();
});

it('rejects duplicate (pull_request_id, file_path) pairs', function () {
    $prId = seedPullRequest();

    foreach ([1, 2] as $i) {
        DB::table('pull_request_files')->insert([
            'id' => (string) Str::uuid(),
            'pull_request_id' => $prId,
            'file_path' => 'config/app.php',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
})->throws(QueryException::class);

it('cascades files when the pull request is deleted', function () {
    $prId = seedPullRequest();

    DB::table('pull_request_files')->insert([
        'id' => (string) Str::uuid(),
        'pull_request_id' => $prId,
        'file_path' => 'routes/web.php',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pull_requests')->where('id', $prId)->delete();

    expect(DB::table('pull_request_files')->count())->toBe(0);
});

it('round-trips the raw_patch text column', function () {
    $prId = seedPullRequest();
    $patch = "diff --git a/config/app.php b/config/app.php\n@@ -1,3 +1,4 @@\n+secret";

    DB::table('pull_request_files')->insert([
        'id' => (string) Str::uuid(),
        'pull_request_id' => $prId,
        'file_path' => 'config/app.php',
        'raw_patch' => $patch,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $file = DB::table('pull_request_files')->where('file_path', 'config/app.php')->first();

    expect($file->raw_patch)->toBe($patch);
});

function seedPullRequest(): string
{
    $orgId = (string) Str::uuid();
    $now = now();

    DB::table('organizations')->insert([
        'id' => $orgId,
        'name' => 'Acme',
        'slug' => 'acme',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $prId = (string) Str::uuid();

    DB::table('pull_requests')->insert([
        'id' => $prId,
        'organization_id' => $orgId,
        'github_repo_id' => 1,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 7,
        'title' => 'Add home controller',
        'author_username' => 'dev',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $prId;
}
