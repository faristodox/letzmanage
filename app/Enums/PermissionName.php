<?php

namespace App\Enums;

enum PermissionName: string
{
    case ManageBranches = 'manage branches';
    case ManagePortfolios = 'manage portfolios';
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
    case ManageEventForms = 'manage event forms';
    case ViewEventResponses = 'view event responses';
    case CheckInEventParticipants = 'check in event participants';
    case ManageForms = 'manage forms';
    case ViewFormResponses = 'view form responses';
    case ManageArchive = 'manage archive';
    case ManageMeetings = 'manage meetings';
    case ManageCommitteeMembers = 'manage committee members';

    public function label(): string
    {
        return match ($this) {
            self::ManageBranches => 'Manage Branches',
            self::ManagePortfolios => 'Manage Portfolios',
            self::ManageUsers => 'Manage Users',
            self::ManageOfficeSpaces => 'Manage Office Spaces',
            self::ManageSettings => 'Manage Settings',
            self::ManageRoles => 'Manage Roles & Permissions',
            self::CreateBookings => 'Create Bookings',
            self::ViewOwnBookings => 'View Own Bookings',
            self::ViewAllBookings => 'View All Bookings',
            self::ApproveBookings => 'Approve Bookings',
            self::CancelAnyBooking => 'Cancel Any Booking',
            self::ViewSpiData => 'View Data Ahli (SPI)',
            self::ManageEventForms => 'Manage Event Forms',
            self::ViewEventResponses => 'View Event Responses',
            self::CheckInEventParticipants => 'Check In Event Participants',
            self::ManageForms => 'Manage Forms',
            self::ViewFormResponses => 'View Form Responses',
            self::ManageArchive => 'Manage Archive',
            self::ManageMeetings => 'Manage Meetings',
            self::ManageCommitteeMembers => 'Manage Committee Members',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ManageBranches, self::ManagePortfolios, self::ManageUsers, self::ManageOfficeSpaces,
            self::ManageSettings, self::ManageRoles => 'Administration',
            self::CreateBookings, self::ViewOwnBookings, self::ViewAllBookings,
            self::ApproveBookings, self::CancelAnyBooking => 'Bookings',
            self::ViewSpiData => 'SPI Data',
            self::ManageEventForms, self::ViewEventResponses, self::CheckInEventParticipants => 'Event Forms',
            self::ManageForms, self::ViewFormResponses => 'Forms',
            self::ManageArchive => 'Archive',
            self::ManageMeetings, self::ManageCommitteeMembers => 'Meetings',
        };
    }
}
