<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'organization_id', 'payment_gateway_enabled', 'chip_brand_id', 'chip_secret_key',
    'bank_transfer_enabled', 'bank_name', 'bank_account_number', 'bank_account_holder',
])]
class OrganizationPaymentSetting extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'payment_gateway_enabled' => 'boolean',
            'bank_transfer_enabled' => 'boolean',
            'chip_secret_key' => 'encrypted',
        ];
    }

    public function hasChipConfigured(): bool
    {
        return $this->payment_gateway_enabled && filled($this->chip_brand_id) && filled($this->chip_secret_key);
    }

    public function hasBankTransferConfigured(): bool
    {
        return $this->bank_transfer_enabled && filled($this->bank_name) && filled($this->bank_account_number);
    }
}
