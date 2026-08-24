<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('boots on Laravel 12', function (): void {
    expect(app()->version())->toStartWith('12.');
});

it('redirects guests from the root to the login page', function (): void {
    $this->get('/')->assertRedirect(route('login'));
});

it('compiles the frontend assets', function (): void {
    expect(File::exists(public_path('build/manifest.json')))->toBeTrue();
});
