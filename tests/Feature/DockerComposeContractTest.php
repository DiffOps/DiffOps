<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

function diffops_compose_yml(): string
{
    return File::get(base_path('docker-compose.yml'));
}

function diffops_dockerfile(string $name): string
{
    return File::get(base_path(".docker/{$name}"));
}

it('defines the fully containerized services', function (): void {
    $yml = diffops_compose_yml();

    expect($yml)
        ->toContain('app:')
        ->toContain('nginx:')
        ->toContain('node:')
        ->toContain('redis:')
        ->toContain('horizon:');
});

it('routes the nginx port to 8080', function (): void {
    expect(diffops_compose_yml())->toContain('8080:80');
});

it('ships app and horizon dockerfiles with required extensions', function (): void {
    $app = diffops_dockerfile('app.Dockerfile');
    $horizon = diffops_dockerfile('horizon.Dockerfile');

    expect($app)
        ->toContain('pdo_pgsql')
        ->toContain('pgsql')
        ->toContain('pdo_sqlite')
        ->toContain('pcntl')
        ->toContain('posix');

    expect($horizon)
        ->toContain('pdo_pgsql')
        ->toContain('pdo_sqlite')
        ->toContain('pcntl')
        ->toContain('posix');
});

it('points redis to the container service name', function (): void {
    $envExample = File::get(base_path('.env.example'));

    expect($envExample)->toContain('REDIS_HOST=redis');
});
