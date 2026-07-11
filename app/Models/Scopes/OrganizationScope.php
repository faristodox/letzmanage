<?php

namespace App\Models\Scopes;

use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters tenant models to the organization active in the CurrentOrganization
 * context. When no organization is active the scope is a no-op.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $orgId = app(CurrentOrganization::class)->id();

        if ($orgId !== null) {
            $builder->where($model->getTable().'.organization_id', $orgId);
        }
    }
}
