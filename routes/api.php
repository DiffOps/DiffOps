<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:supabase')->get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
