<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class BookingConflictException extends RuntimeException
{
    public function __construct(string $message = 'The selected time slot is no longer available.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): ?Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        return null;
    }
}
