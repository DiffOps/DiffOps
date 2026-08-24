<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Web\BriefingController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\IncursionController;
use App\Http\Controllers\Web\OperationsLogController;
use App\Http\Controllers\Web\RepositoryController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\WatchlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Inertia)
|--------------------------------------------------------------------------
| Protected by verify.supabase.jwt middleware for JWT stateless auth
*/

// Guest routes (login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::get('/register', [LoginController::class, 'showRegister'])->name('register');
});

// Protected routes
Route::middleware(['verify.supabase.jwt'])->group(function () {
    // Dashboard (raiz, conforme DiffOps.md §13)
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.alias');

    // Incursions (PR analyses)
    Route::get('/incursions', [IncursionController::class, 'index'])->name('incursions.index');
    Route::get('/incursions/{analysis}', [IncursionController::class, 'show'])->name('incursions.show');
    Route::post('/incursions/{analysis}/rescan', [IncursionController::class, 'rescan'])->name('incursions.rescan');
    Route::post('/incursions/{analysis}/comment', [IncursionController::class, 'commentOnPr'])->name('incursions.comment');

    // Repositories
    Route::get('/repos', [RepositoryController::class, 'index'])->name('repos.index');
    Route::post('/repos', [RepositoryController::class, 'store'])->name('repos.store');
    Route::get('/repos/{repository}', [RepositoryController::class, 'show'])->name('repos.show');
    Route::put('/repos/{repository}', [RepositoryController::class, 'update'])->name('repos.update');
    Route::delete('/repos/{repository}', [RepositoryController::class, 'destroy'])->name('repos.destroy');

    // Operations Log (Combat History)
    Route::get('/operations-log', [OperationsLogController::class, 'index'])->name('operations-log.index');
    Route::get('/operations-log/export', [OperationsLogController::class, 'export'])->name('operations-log.export');

    // Briefing (Analytics)
    Route::get('/briefing', [BriefingController::class, 'index'])->name('briefing.index');

    // Watchlist
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/watchlist/{repository}', [WatchlistController::class, 'toggle'])->name('watchlist.toggle');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

});

// Fallback: URL desconhecida volta ao fluxo de auth
Route::fallback(fn () => redirect()->route('login'));
