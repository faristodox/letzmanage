<?php

namespace App\Enums;

/**
 * An Event's own lifecycle — independent of its registration form's status
 * (EventFormStatus). An event can be Published (real, on calendars) while
 * its registration form is still Draft or already Closed for sign-ups.
 */
enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
}
