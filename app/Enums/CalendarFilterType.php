<?php

namespace App\Enums;

enum CalendarFilterType: string
{
    case All = 'all';
    case Booking = 'booking';
    case Event = 'event';
}
