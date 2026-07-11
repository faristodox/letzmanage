<?php

namespace App\Enums;

/**
 * IKRAM Kuala Lumpur kawasan and their SPI `memberdistrict` codes, read directly
 * from SPI's own dropdown. Used to scope an organization's SPI data.
 */
enum SpiKawasan: string
{
    case Batu = '35';
    case Setiawangsa = '36';
    case BandarTunRazak = '37';
    case LembahPantai = '38';
    case WangsaMaju = '84';
    case Titiwangsa = '85';

    public function label(): string
    {
        return match ($this) {
            self::Batu           => 'IKRAM Batu',
            self::Setiawangsa    => 'IKRAM Setiawangsa',
            self::BandarTunRazak => 'IKRAM Bandar Tun Razak',
            self::LembahPantai   => 'IKRAM Lembah Pantai',
            self::WangsaMaju     => 'IKRAM Wangsa Maju',
            self::Titiwangsa     => 'IKRAM Titiwangsa',
        };
    }

    public static function labelFor(?string $code): ?string
    {
        return $code ? self::tryFrom($code)?->label() : null;
    }
}
