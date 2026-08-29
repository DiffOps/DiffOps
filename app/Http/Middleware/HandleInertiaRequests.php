<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // NOTE: `share()` is invoked by the Inertia middleware during the
        // incoming phase of the `web` group — BEFORE `verify.supabase.jwt`
        // authenticates the stateless supabase guard. Reading $request->user()
        // here would force an early (null) resolution that the guard caches,
        // making the route middleware treat the user as a guest. So the org
        // context is computed lazily inside a closure, resolved only when the
        // Inertia response is rendered (after auth/middleware have run).
        return [
            ...parent::share($request),
            'organizations' => function () use ($request) {
                $user = $request->user('supabase');

                if ($user === null) {
                    return [];
                }

                return $user->organizations()
                    ->get(['organizations.id', 'organizations.name', 'organizations.slug'])
                    ->map(fn ($org) => [
                        'id' => $org->id,
                        'name' => $org->name,
                        'slug' => $org->slug,
                    ])
                    ->values()
                    ->all();
            },
            'currentOrganization' => function () use ($request) {
                $user = $request->user('supabase');

                if ($user === null) {
                    return null;
                }

                $active = $user->currentOrganization;

                return $active === null ? null : [
                    'id' => $active->id,
                    'name' => $active->name,
                    'slug' => $active->slug,
                ];
            },
        ];
    }
}
