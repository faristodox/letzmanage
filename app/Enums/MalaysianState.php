<?php

namespace App\Enums;

/**
 * The 13 states plus the 3 federal territories — used to pick which state's
 * school-holiday page to scrape from cutisekolah.com.my (see
 * App\Services\CutiSekolahHolidayService). Enum values are the exact URL
 * slugs the site uses, e.g. https://cutisekolah.com.my/kalendar-akademik-2026/kuala-lumpur/.
 */
enum MalaysianState: string
{
    case Johor = 'johor';
    case Kedah = 'kedah';
    case Kelantan = 'kelantan';
    case Melaka = 'melaka';
    case NegeriSembilan = 'negeri-sembilan';
    case Pahang = 'pahang';
    case Perak = 'perak';
    case Perlis = 'perlis';
    case PulauPinang = 'pulau-pinang';
    case Sabah = 'sabah';
    case Sarawak = 'sarawak';
    case Selangor = 'selangor';
    case Terengganu = 'terengganu';
    case KualaLumpur = 'kuala-lumpur';
    case Labuan = 'labuan';
    case Putrajaya = 'putrajaya';

    public function label(): string
    {
        return match ($this) {
            self::Johor => 'Johor',
            self::Kedah => 'Kedah',
            self::Kelantan => 'Kelantan',
            self::Melaka => 'Melaka',
            self::NegeriSembilan => 'Negeri Sembilan',
            self::Pahang => 'Pahang',
            self::Perak => 'Perak',
            self::Perlis => 'Perlis',
            self::PulauPinang => 'Pulau Pinang',
            self::Sabah => 'Sabah',
            self::Sarawak => 'Sarawak',
            self::Selangor => 'Selangor',
            self::Terengganu => 'Terengganu',
            self::KualaLumpur => 'Kuala Lumpur',
            self::Labuan => 'Labuan',
            self::Putrajaya => 'Putrajaya',
        };
    }
}
