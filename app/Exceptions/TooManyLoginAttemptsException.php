<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\MessageBag;

/**
 * Ném khi đăng nhập sai quá số lần cho phép.
 * Render HTTP 429 kèm lỗi ở ô `login` — đúng yêu cầu bảo mật.
 */
class TooManyLoginAttemptsException extends Exception
{
    public function __construct(public readonly string $messageText, public readonly int $retryAfter = 900)
    {
        parent::__construct($messageText);
    }

    public function errors(): MessageBag
    {
        return new MessageBag(['login' => [$this->messageText]]);
    }
}
