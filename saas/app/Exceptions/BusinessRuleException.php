<?php

namespace App\Exceptions;

use RuntimeException;

/** A domain rule was violated; the message is user-facing (Arabic, translatable). */
class BusinessRuleException extends RuntimeException
{
}
