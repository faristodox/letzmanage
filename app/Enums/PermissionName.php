<?php

namespace App\Enums;

enum PermissionName: string
{
    case ManageBranches = 'manage branches';
    case ManageUsers = 'manage users';
    case ManageOfficeSpaces = 'manage office spaces';
    case ManageSettings = 'manage settings';
    case CreateBookings = 'create bookings';
    case ViewOwnBookings = 'view own bookings';
    case ViewAllBookings = 'view all bookings';
    case ApproveBookings = 'approve bookings';
    case CancelAnyBooking = 'cancel any booking';
}
