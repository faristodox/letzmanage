<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A committee/wing grouping (e.g. "Jawatankuasa WANITA") — see
 * App\Models\User::portfolio() / App\Models\Event::portfolio() for how
 * accounts and records get scoped to one.
 */
#[Fillable(['organization_id', 'name'])]
class Portfolio extends Model
{
    use BelongsToOrganization, HasFactory;
}
