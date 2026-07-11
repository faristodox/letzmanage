<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to the SPI module unless the current organization has the
 * (IKRAM-specific) SPI feature enabled.
 */
class EnsureSpiEnabled
{
    public function __construct(private CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->current->get()?->hasSpiEnabled(), 403);

        return $next($request);
    }
}
