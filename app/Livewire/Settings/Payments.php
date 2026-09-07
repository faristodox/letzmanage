<?php

namespace App\Livewire\Settings;

use App\Models\OrganizationPaymentSetting;
use Livewire\Component;

class Payments extends Component
{
    public bool $paymentGatewayEnabled = false;

    public string $chipBrandId = '';

    public string $chipSecretKey = '';

    public bool $hasStoredChipSecretKey = false;

    public bool $bankTransferEnabled = false;

    public string $bankName = '';

    public string $bankAccountNumber = '';

    public string $bankAccountHolder = '';

    public function mount(): void
    {
        $this->authorize('viewAny', OrganizationPaymentSetting::class);

        $setting = auth()->user()->organization?->paymentSetting;

        $this->paymentGatewayEnabled = (bool) $setting?->payment_gateway_enabled;
        $this->chipBrandId = $setting?->chip_brand_id ?? '';
        $this->hasStoredChipSecretKey = filled($setting?->chip_secret_key);
        $this->bankTransferEnabled = (bool) $setting?->bank_transfer_enabled;
        $this->bankName = $setting?->bank_name ?? '';
        $this->bankAccountNumber = $setting?->bank_account_number ?? '';
        $this->bankAccountHolder = $setting?->bank_account_holder ?? '';
    }

    public function save(): void
    {
        $this->authorize('update', new OrganizationPaymentSetting);

        $data = $this->validate([
            'paymentGatewayEnabled' => ['boolean'],
            'chipBrandId' => [$this->paymentGatewayEnabled ? 'required' : 'nullable', 'string', 'max:255'],
            'chipSecretKey' => ['nullable', 'string', 'max:255'],
            'bankTransferEnabled' => ['boolean'],
            'bankName' => [$this->bankTransferEnabled ? 'required' : 'nullable', 'string', 'max:255'],
            'bankAccountNumber' => [$this->bankTransferEnabled ? 'required' : 'nullable', 'string', 'max:255'],
            'bankAccountHolder' => [$this->bankTransferEnabled ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        if ($this->paymentGatewayEnabled && ! $this->hasStoredChipSecretKey && ! $data['chipSecretKey']) {
            $this->addError('chipSecretKey', __('Enter your CHIP Secret Key.'));

            return;
        }

        $organization = auth()->user()->organization;

        $payload = [
            'payment_gateway_enabled' => $data['paymentGatewayEnabled'],
            'chip_brand_id' => $data['chipBrandId'] ?: null,
            'bank_transfer_enabled' => $data['bankTransferEnabled'],
            'bank_name' => $data['bankName'] ?: null,
            'bank_account_number' => $data['bankAccountNumber'] ?: null,
            'bank_account_holder' => $data['bankAccountHolder'] ?: null,
        ];

        if ($data['chipSecretKey']) {
            $payload['chip_secret_key'] = $data['chipSecretKey'];
            $this->hasStoredChipSecretKey = true;
        }

        $organization->paymentSetting()->updateOrCreate([], $payload);

        $this->chipSecretKey = '';

        $this->dispatch('settings-saved');
    }

    public function render()
    {
        return view('livewire.settings.payments');
    }
}
