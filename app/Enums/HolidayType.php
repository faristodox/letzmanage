<?php

namespace App\Enums;

enum HolidayType: string
{
    case Public = 'public';
    case School = 'school';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public Holiday',
            self::School => 'School Holiday',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Public => 'bg-red-50 text-red-700',
            self::School => 'bg-sky-50 text-sky-700',
        };
    }
}
