<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpiMember extends Model
{
    protected $fillable = [
        'no_ahli',
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
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'umur'      => 'integer',
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
