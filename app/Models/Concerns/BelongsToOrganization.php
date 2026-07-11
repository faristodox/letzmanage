<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as belonging to one organization (tenant).
 *
 * - Adds a global scope that filters every query to the current organization
 *   (resolved from the CurrentOrganization context). When no organization is
 *   active (console, super-admin platform pages, unauthenticated non-tenant
 *   requests) the scope is a no-op, so nothing is hidden unexpectedly.
 * - Auto-stamps organization_id on create from the current context when the
 *   attribute has not been set explicitly.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model): void {
            if ($model->organization_id === null) {
                $model->organization_id = app(CurrentOrganization::class)->id();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Query without the organization scope (use sparingly, e.g. super-admin).
     */
    public function scopeAcrossOrganizations(Builder $query): Builder
    {
        return $query->withoutGlobalScope(OrganizationScope::class);
    }
}
