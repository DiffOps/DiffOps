<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display user settings.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $profile = $user->profile;

        return Inertia::render('Settings/Index', [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'role' => $profile?->role ?? 'operator',
            ],
            'preferences' => [
                'theme' => 'tactical', // fixed for now
                'notifications' => [
                    'email' => $user->email_notifications ?? false,
                    'push' => $user->push_notifications ?? false,
                    'realtime' => true,
                ],
                'language' => 'pt-BR',
                'timezone' => 'UTC',
            ],
        ]);
    }

    /**
     * Update user settings.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $user->update([
            'username' => $validated['username'] ?? $user->username,
            'email' => $validated['email'] ?? $user->email,
            'email_notifications' => $validated['preferences']['notifications']['email'] ?? false,
            'push_notifications' => $validated['preferences']['notifications']['push'] ?? false,
        ]);

        return back()->with('success', 'Preferências atualizadas.');
    }
}