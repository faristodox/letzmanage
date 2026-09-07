<?php

namespace App\Models;

use App\Enums\FormStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'title', 'slug', 'description', 'status', 'closes_at', 'created_by'])]
class Form extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'closes_at' => 'datetime',
        ];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAcceptingResponses(): bool
    {
        if ($this->status !== FormStatus::Published) {
            return false;
        }

        return $this->closes_at === null || $this->closes_at->isFuture();
    }

    public function publicUrl(): string
    {
        return route('form-submission.show', ['organization' => $this->organization, 'formSlug' => $this->slug]);
    }

    /**
     * Generate a slug unique within the current organization, mirroring
     * Event::uniqueSlug() for globally-unique org slugs.
     */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'form';
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
