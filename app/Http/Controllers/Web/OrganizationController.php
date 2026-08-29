<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * Switch the active organization context for the web UI.
     *
     * The choice is persisted in the unencrypted diffops_org cookie (see
     * bootstrap/app.php encryptCookies except list) so the EnsureOrganization
     * middleware can normalize it on subsequent requests.
     */
    public function switch(Request $request): RedirectResponse
    {
        $user = auth('supabase')->user();

        $organization = $user->organizations()
            ->whereKey($request->input('organization_id'))
            ->first();

        if (! $organization instanceof Organization) {
            return back()->withErrors([
                'organization_id' => 'Organização inválida ou fora do seu acesso.',
            ]);
        }

        return back()
            ->withCookie(cookie()->forever('diffops_org', $organization->id))
            ->with('success', 'Organização ativa atualizada.');
    }
}
