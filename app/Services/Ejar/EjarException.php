<?php

namespace App\Services\Ejar;

use RuntimeException;

/** Ejar refused or could not be reached; $messages carries Ejar's own explanations (Arabic when given). */
class EjarException extends RuntimeException
{
    /** @param list<string> $messages */
    public function __construct(string $message, public readonly array $messages = [], public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
