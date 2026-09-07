<?php

namespace App\Enums;

enum SettingKey: string
{
    case BookingApprovalMode = 'booking_approval_mode';
    case ApprovalEmailNote = 'approval_email_note';
    case OrganizationName = 'organization_name';
    case OrganizationLogo = 'organization_logo';
    case TelegramNotificationsEnabled = 'telegram_notifications_enabled';
    case EmailNotificationsEnabled = 'email_notifications_enabled';
}
