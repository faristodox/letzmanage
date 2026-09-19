<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';
    case CommitteeMember = 'committee_member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Staff => 'Staff',
            self::CommitteeMember => 'Committee Member',
        };
    }
}
