<?php

namespace App\Services;

use App\Enums\PlatformSettingKey;
use App\Models\PlatformSetting;

class PlatformSettingService
{
    public function get(PlatformSettingKey $key, ?string $default = null): ?string
    {
        return PlatformSetting::where('key', $key->value)->value('value') ?? $default;
    }

    public function set(PlatformSettingKey $key, string $value): void
    {
        PlatformSetting::updateOrCreate(
            ['key' => $key->value],
            ['value' => $value],
        );
    }

    public function isPublicSignupEnabled(): bool
    {
        // Defaults to enabled when unset.
        return $this->get(PlatformSettingKey::PublicSignupEnabled, '1') === '1';
    }

    public function setPublicSignupEnabled(bool $enabled): void
    {
        $this->set(PlatformSettingKey::PublicSignupEnabled, $enabled ? '1' : '0');
    }
}
