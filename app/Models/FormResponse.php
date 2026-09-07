<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'form_id', 'reference', 'answers', 'submitted_ip'])]
class FormResponse extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'answers' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $response): void {
            $response->update(['reference' => sprintf('RESP-%06d', $response->id)]);
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
