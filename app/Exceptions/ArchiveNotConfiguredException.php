<?php

namespace App\Exceptions;

use RuntimeException;

class ArchiveNotConfiguredException extends RuntimeException
{
    public function __construct(string $message = 'File Archive is not set up yet — connect a Google account and enable it in Settings.')
    {
        parent::__construct($message);
    }
}
