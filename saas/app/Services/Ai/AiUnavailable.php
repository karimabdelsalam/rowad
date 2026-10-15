<?php

namespace App\Services\Ai;

use RuntimeException;

/** The assistant cannot answer right now; the message is safe to show to the user. */
class AiUnavailable extends RuntimeException
{
}
