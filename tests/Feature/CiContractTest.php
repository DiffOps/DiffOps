<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

function diffops_ci_yml(): string
{
    return File::get(base_path('.github/workflows/ci.yml'));
}

it('defines the github actions workflow file', function (): void {
    expect(File::exists(base_path('.github/workflows/ci.yml')))->toBeTrue();
});

it('runs a self-contained ci job', function (): void {
    $yml = diffops_ci_yml();

    expect($yml)
        ->toContain('ci:')
        ->not->toContain('frontend:');
});

it('executes pint and the pest suite in the ci job', function (): void {
    $yml = diffops_ci_yml();

    expect($yml)
        ->toContain('pint --test')
        ->toContain('php artisan test');
});

it('builds the frontend assets before the tests', function (): void {
    expect(diffops_ci_yml())->toContain('npm run build');
});

it('keeps the pipeline offline without secrets', function (): void {
    $yml = diffops_ci_yml();

    expect($yml)
        ->not->toContain('DB_PASSWORD')
        ->not->toContain('SUPABASE')
        ->not->toContain('OPENROUTER')
        ->not->toContain('GITHUB_TOKEN')
        ->not->toContain('GITHUB_APP_PRIVATE_KEY')
        ->not->toContain('GITHUB_WEBHOOK_SECRET')
        ->not->toContain('BEGIN RSA')
        ->not->toContain('secrets.');
});
