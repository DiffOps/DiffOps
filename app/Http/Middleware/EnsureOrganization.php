<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganization
{
    /**
     * Normalize the active organization for the web UI.
     *
     * Reads the diffops_org cookie; if it points to an organization the user
     * is a member of, that becomes the active context. Otherwise we fall back
     * to the user's first membership (or null when they belong to none).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('supabase')->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $organization = null;
        $orgId = $request->cookie('diffops_org');

        if ($orgId !== null) {
            $organization = $user->organizations()->whereKey($orgId)->first();
        }

        if ($organization === null) {
            $organization = $user->organizations()->first();
        }

        $user->setCurrentOrganization($organization);

        return $next($request);
    }
}
