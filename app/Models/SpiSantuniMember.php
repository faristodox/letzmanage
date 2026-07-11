<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class SpiSantuniMember extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'nama',
        'no_kp',
        'keahlian',
        'umur',
        'peringkat',
        'jantina',
        'kategori',
        'negeri',
        'kawasan',
        'tarikh_semak',
        'tarikh_lulus',
        'synced_at',
    ];

    protected $casts = [
        'umur' => 'integer',
        'synced_at' => 'datetime',
    ];

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
