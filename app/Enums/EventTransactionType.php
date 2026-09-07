<?php

namespace App\Enums;

enum EventTransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
}
