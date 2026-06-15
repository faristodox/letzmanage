<?php

namespace App\Services;

use App\Enums\ApprovalMode;
use App\Enums\SettingKey;
use App\Models\SystemSetting;

class SystemSettingService
{
    public function get(SettingKey $key, ?int $branchId = null, ?string $default = null): ?string
    {
        $value = SystemSetting::where('key', $key->value)
            ->where('branch_id', $branchId)
            ->value('value');

        if ($value !== null) {
            return $value;
        }

        return $branchId !== null
            ? $this->get($key, null, $default)
            : $default;
    }

    public function set(SettingKey $key, string $value, ?int $branchId = null): SystemSetting
    {
        return SystemSetting::updateOrCreate(
            ['key' => $key->value, 'branch_id' => $branchId],
            ['value' => $value]
        );
    }

    public function getApprovalMode(?int $branchId = null): ApprovalMode
    {
        $value = $this->get(SettingKey::BookingApprovalMode, $branchId, ApprovalMode::Manual->value);

        return ApprovalMode::from($value);
    }

    public function setApprovalMode(ApprovalMode $mode, ?int $branchId = null): void
    {
        $this->set(SettingKey::BookingApprovalMode, $mode->value, $branchId);
    }

    public function getApprovalEmailNote(?int $branchId = null): ?string
    {
        return $this->get(SettingKey::ApprovalEmailNote, $branchId);
    }

    public function setApprovalEmailNote(?string $note, ?int $branchId = null): void
    {
        if ($note === null || $note === '') {
            SystemSetting::where('branch_id', $branchId)
                ->where('key', SettingKey::ApprovalEmailNote->value)
                ->delete();

            return;
        }

        $this->set(SettingKey::ApprovalEmailNote, $note, $branchId);
    }

    public function getOrganizationName(): ?string
    {
        return $this->get(SettingKey::OrganizationName);
    }

    public function setOrganizationName(?string $name): void
    {
        if ($name === null || $name === '') {
            SystemSetting::where('branch_id', null)
                ->where('key', SettingKey::OrganizationName->value)
                ->delete();

            return;
        }

        $this->set(SettingKey::OrganizationName, $name);
    }

    public function getOrganizationLogoPath(): ?string
    {
        return $this->get(SettingKey::OrganizationLogo);
    }

    public function setOrganizationLogoPath(?string $path): void
    {
        if ($path === null || $path === '') {
            SystemSetting::where('branch_id', null)
                ->where('key', SettingKey::OrganizationLogo->value)
                ->delete();

            return;
        }

        $this->set(SettingKey::OrganizationLogo, $path);
    }
}
