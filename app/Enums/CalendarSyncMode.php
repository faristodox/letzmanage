<?php

namespace App\Enums;

enum CalendarSyncMode: string
{
    case Disabled = 'disabled';
    case Shared = 'shared';
    case Individual = 'individual';
}
