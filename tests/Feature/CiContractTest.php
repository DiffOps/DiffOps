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

it('runs backend and frontend jobs', function (): void {
    $yml = diffops_ci_yml();

    expect($yml)
        ->toContain('backend:')
        ->toContain('frontend:');
});

it('executes pint and the pest suite in the backend job', function (): void {
    $yml = diffops_ci_yml();

    expect($yml)
        ->toContain('pint --test')
        ->toContain('php artisan test');
});

it('builds the frontend assets in the frontend job', function (): void {
    expect(diffops_ci_yml())->toContain('npm run build');
});

it('keeps the pipeline offline without secrets', function (): void {
    $yml = diffops_ci_yml();

    expect($yml)
        ->not->toContain('DB_PASSWORD')
        ->not->toContain('SUPABASE')
        ->not->toContain('OPENROUTER')
        ->not->toContain('GITHUB_TOKEN')
        ->not->toContain('secrets.');
});
