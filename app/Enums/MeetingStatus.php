<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Transcribing = 'transcribing';
    case Summarizing = 'summarizing';
    case Ready = 'ready';
    case Failed = 'failed';
}
