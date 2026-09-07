<?php

namespace App\Enums;

enum EventTransactionSource: string
{
    case Manual = 'manual';
    case Gateway = 'gateway';
    case BankTransfer = 'bank_transfer';
}
