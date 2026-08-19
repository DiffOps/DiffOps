<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GitHubWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:supabase')->get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');

Route::post('/webhooks/github', [GitHubWebhookController::class, 'handle'])
    ->middleware('validate.github.signature')
    ->name('api.webhooks.github');
