<?php

namespace App\Enums;

enum HolidaySource: string
{
    case Google = 'google';
    case CutiSekolah = 'cutisekolah';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google Calendar',
            self::CutiSekolah => 'Other Source',
        };
    }
}
