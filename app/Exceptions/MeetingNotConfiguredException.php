<?php

namespace App\Exceptions;

use RuntimeException;

class MeetingNotConfiguredException extends RuntimeException
{
    public function __construct(string $message = 'Meeting transcription is not set up yet — the Google Cloud credentials are missing on the server.')
    {
        parent::__construct($message);
    }
}
