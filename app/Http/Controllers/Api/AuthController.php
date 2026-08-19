<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * The authenticated user's tactical profile and organization memberships.
     *
     * The route is protected by the supabase guard, so the user is always
     * present; the guard is still re-checked to satisfy static analysis.
     */
    public function me(): JsonResponse
    {
        $user = auth('supabase')->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'data' => [
                'supabase_uid' => $user->supabase_uid,
                'name' => $user->name,
                'email' => $user->email,
                'github_username' => $user->github_username,
                'avatar_url' => $user->avatar_url,
                'is_commander' => $user->is_commander,
                'last_login_at' => $user->last_login_at?->toISOString(),
                'organizations' => $user->memberships()
                    ->with('organization')
                    ->get()
                    ->sortBy(fn (OrganizationMember $membership) => $membership->organization->name)
                    ->values()
                    ->map(fn (OrganizationMember $membership) => [
                        'id' => $membership->organization_id,
                        'name' => $membership->organization->name,
                        'role' => $this->roleFor($user, $membership)->value,
                    ]),
            ],
        ]);
    }

    private function roleFor(User $user, OrganizationMember $membership): OrganizationRole
    {
        if ($user->is_commander && $membership->role === null) {
            return OrganizationRole::Commander;
        }

        return $membership->role;
    }
}
