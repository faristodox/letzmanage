<?php

namespace App\Enums;

enum EventPaymentMethod: string
{
    case Chip = 'chip';
    case BankTransfer = 'bank_transfer';
}
