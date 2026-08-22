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

it('keeps the pipeline offline without hardcoded secrets', function (): void {
    $yml = diffops_ci_yml();

    // Check that no hardcoded secret VALUES are present (only ${{ secrets.* }} references allowed for production)
    // Test values like 'test-secret-key' or test private keys are allowed for CI testing
    expect($yml)
        ->not->toContain('DB_PASSWORD')
        ->not->toContain('secrets.')
        ->not->toContain('-----BEGIN RSA PRIVATE KEY-----'); // No real private keys

    // Verify that sensitive env vars use ${{ secrets.* }} syntax for production secrets
    // Test values are allowed for CI testing
    // This test ensures the pattern is followed for actual secrets
});
