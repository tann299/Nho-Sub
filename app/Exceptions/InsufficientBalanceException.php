<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $available)
    {
        parent::__construct(
            'Số dư không đủ. Cần ' . number_format($required, 0, ',', '.')
            . ' ₫, hiện có ' . number_format($available, 0, ',', '.') . ' ₫.'
        );
    }
}
