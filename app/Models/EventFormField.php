<?php

namespace App\Models;

use App\Enums\EventFormFieldType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'event_form_id', 'label', 'type', 'options', 'option_prices', 'required', 'help_text', 'order'])]
class EventFormField extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => EventFormFieldType::class,
            'options' => 'array',
            'option_prices' => 'array',
            'required' => 'boolean',
        ];
    }

    public function eventForm(): BelongsTo
    {
        return $this->belongsTo(EventForm::class);
    }
}
