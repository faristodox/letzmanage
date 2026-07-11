<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class SpiMember extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'no_ahli',
        'spi_uid',
        'nama',
        'no_kp',
        'umur',
        'jantina',
        'kategori',
        'kawasan',
        'no_tel',
        'level',
        'naqib',
        'usrah_label',
        'jawatankuasa',
        'usrah_dibawa',
        'penglibatan_amal',
        'synced_at',
    ];

    protected $casts = [
        'synced_at'        => 'datetime',
        'umur'             => 'integer',
        'jawatankuasa'     => 'array',
        'usrah_dibawa'     => 'array',
        'penglibatan_amal' => 'array',
    ];

    public static function levelLabel(string $level): string
    {
        return match ($level) {
            '03' => 'Modul 03 (AA)',
            '04' => 'Modul 04 (AA)',
            '05' => 'Modul 05 (AT)',
            default => "Level {$level}",
        };
    }

    public function maskedNoKp(): string
    {
        if (! $this->no_kp) {
            return '—';
        }
        $clean = preg_replace('/\D/', '', $this->no_kp);

        return strlen($clean) >= 6
            ? substr($clean, 0, 6).'–****'
            : '••••••';
    }
}
