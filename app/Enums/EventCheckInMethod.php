<?php

namespace App\Enums;

enum EventCheckInMethod: string
{
    case Qr = 'qr';
    case Link = 'link';
    case Manual = 'manual';
    case Onsite = 'onsite';
}
