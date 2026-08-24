<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GitHubWebhookController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/session', [SessionController::class, 'store'])->name('api.auth.session.store');
Route::delete('/auth/session', [SessionController::class, 'destroy'])->name('api.auth.session.destroy');

Route::middleware('auth:supabase')->get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');

Route::post('/webhooks/github', [GitHubWebhookController::class, 'handle'])
    ->middleware('validate.github.signature')
    ->name('api.webhooks.github');
