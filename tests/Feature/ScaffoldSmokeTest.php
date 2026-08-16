<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('boots on Laravel 12', function (): void {
    expect(app()->version())->toStartWith('12.');
});

it('serves the root route', function (): void {
    $this->get('/')->assertOk();
});

it('compiles the frontend assets', function (): void {
    expect(File::exists(public_path('build/manifest.json')))->toBeTrue();
});

it('renders the Welcome page through Inertia', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('exposes the DiffOps app name to the page', function (): void {
    $this->get('/')
        ->assertInertia(fn ($page) => $page->component('Welcome')->where('appName', 'DiffOps'));
});
