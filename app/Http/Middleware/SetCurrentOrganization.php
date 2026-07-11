<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active organization (tenant) for authenticated web requests
 * from the logged-in user and stores it in the CurrentOrganization context,
 * which the BelongsToOrganization global scope reads to filter every query.
 *
 * Guest requests (login, the public booking page) set no organization here;
 * the public booking page resolves its org from the URL slug separately.
 */
class SetCurrentOrganization
{
    public function __construct(private CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->organization_id) {
            $organization = Organization::find($user->organization_id);

            if ($organization) {
                $this->current->set($organization);
            }
        }

        return $next($request);
    }
}
