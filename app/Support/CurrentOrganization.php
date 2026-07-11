<?php

namespace App\Support;

use App\Models\Organization;

/**
 * Holds the organization (tenant) active for the current request/process.
 *
 * Resolved once per request by SetCurrentOrganization middleware (from the
 * authenticated user) or explicitly (e.g. the public booking page from a slug,
 * or a console command targeting a specific org). The BelongsToOrganization
 * global scope reads from here to filter every tenant query.
 *
 * Registered as a singleton so it lives for the lifetime of one request/process.
 */
class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->id;
    }

    public function isSet(): bool
    {
        return $this->organization !== null;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    /**
     * Run a callback with a given organization as the active tenant, restoring
     * the previous one afterwards. Useful for console/jobs operating per-tenant.
     */
    public function runFor(Organization $organization, callable $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;

        try {
            return $callback();
        } finally {
            $this->organization = $previous;
        }
    }
}
