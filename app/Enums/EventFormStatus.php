<?php

namespace App\Enums;

enum EventFormStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
}
