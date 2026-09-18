<?php

namespace App\Enums;

/**
 * Distinguishes a normal public Event (registration form, fields, payment,
 * registration-based check-in) from a Committee Meeting (no registration —
 * check-in verifies against the Committee Members roster by IC number
 * instead). Both share the same Event scheduling/calendar infrastructure.
 */
enum EventType: string
{
    case Event = 'event';
    case CommitteeMeeting = 'committee_meeting';
}
