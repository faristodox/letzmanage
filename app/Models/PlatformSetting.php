<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide setting (not scoped to any organization). Intentionally does
 * NOT use BelongsToOrganization — these apply across all tenants.
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];
}
