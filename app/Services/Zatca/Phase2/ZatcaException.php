<?php

namespace App\Services\Zatca\Phase2;

use RuntimeException;

/** ZATCA refused or could not be reached; carries its validation messages for the screen. */
class ZatcaException extends RuntimeException
{
    /** @param list<string> $messages */
    public function __construct(string $message, public readonly array $messages = [], public readonly ?int $status = null, public readonly array $response = [])
    {
        parent::__construct($message);
    }
}
