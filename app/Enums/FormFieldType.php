<?php

namespace App\Enums;

enum FormFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Email = 'email';
    case Phone = 'phone';
    case Date = 'date';
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case File = 'file';

    /**
     * Whether this field type is answered by picking from a fixed set of options
     * (as opposed to free-form input) — these are the fields auto-charted.
     */
    public function isChoice(): bool
    {
        return match ($this) {
            self::Select, self::Radio, self::Checkbox => true,
            default => false,
        };
    }

    /**
     * Whether a response can select more than one option (affects chart type:
     * a single pick fits a pie chart, multiple picks don't sum to 100%).
     */
    public function isMultiple(): bool
    {
        return $this === self::Checkbox;
    }
}
