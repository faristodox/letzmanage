<?php

namespace App\Enums;

enum CheckInVerificationMode: string
{
    case Any = 'any';
    case All = 'all';
}
