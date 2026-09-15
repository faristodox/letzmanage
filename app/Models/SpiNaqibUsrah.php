<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class SpiNaqibUsrah extends Model
{
    use BelongsToOrganization;

    protected $table = 'spi_naqib_usrah';

    protected $fillable = [
        'organization_id',
        'usrah_id',
        'level',
        'usrah_name',
        'status',
        'naqib_name',
        'is_temporary_group',
        'jenis',
        'kategori',
        'tarikh_mula',
        'tarikh_bubar',
        'nota',
        'negeri',
        'kawasan',
        'member_count',
        'members',
        'synced_at',
    ];

    protected $casts = [
        'is_temporary_group' => 'boolean',
        'member_count' => 'integer',
        'members' => 'array',
        'synced_at' => 'datetime',
    ];

    public function displayName(): string
    {
        return $this->naqib_name ?: 'Belum ada naqib (grup sementara)';
    }
}
