<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name'])]
class OfficeSpaceType extends Model
{
    use BelongsToOrganization, HasFactory;

    public function officeSpaces(): HasMany
    {
        return $this->hasMany(OfficeSpace::class, 'type_id');
    }
}
