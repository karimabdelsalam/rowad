<?php

namespace App\Services\Messaging;

final class SmsResult
{
    private function __construct(public readonly bool $ok, public readonly ?string $error = null)
    {
    }

    public static function sent(): self
    {
        return new self(true);
    }

    public static function failed(string $error): self
    {
        return new self(false, $error);
    }
}
