<?php

namespace App\Exceptions;

use RuntimeException;

/** Lỗi Napxu đã bỏ bí mật; ambiguous = chưa biết giao dịch có thực hiện hay không. */
class NapxuException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $ambiguous = false)
    {
        parent::__construct($message);
    }
}
