<?php

namespace App\Enums;

enum PermissionName: string
{
    case ManageBranches = 'manage branches';
    case ManageUsers = 'manage users';
    case ManageOfficeSpaces = 'manage office spaces';
    case ManageSettings = 'manage settings';
    case ManageRoles = 'manage roles';
    case CreateBookings = 'create bookings';
    case ViewOwnBookings = 'view own bookings';
    case ViewAllBookings = 'view all bookings';
    case ApproveBookings = 'approve bookings';
    case CancelAnyBooking = 'cancel any booking';
    case ViewSpiData = 'view spi data';

    public function label(): string
    {
        return match ($this) {
            self::ManageBranches    => 'Manage Branches',
            self::ManageUsers       => 'Manage Users',
            self::ManageOfficeSpaces => 'Manage Office Spaces',
            self::ManageSettings    => 'Manage Settings',
            self::ManageRoles       => 'Manage Roles & Permissions',
            self::CreateBookings    => 'Create Bookings',
            self::ViewOwnBookings   => 'View Own Bookings',
            self::ViewAllBookings   => 'View All Bookings',
            self::ApproveBookings   => 'Approve Bookings',
            self::CancelAnyBooking  => 'Cancel Any Booking',
            self::ViewSpiData       => 'View Data Ahli (SPI)',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ManageBranches, self::ManageUsers, self::ManageOfficeSpaces,
            self::ManageSettings, self::ManageRoles => 'Administration',
            self::CreateBookings, self::ViewOwnBookings, self::ViewAllBookings,
            self::ApproveBookings, self::CancelAnyBooking => 'Bookings',
            self::ViewSpiData => 'SPI Data',
        };
    }
}
