<?php

namespace App\Exceptions;

use RuntimeException;

class RefundLimitExceededException extends RuntimeException
{
    public function __construct(public readonly int $requested, public readonly int $cap)
    {
        parent::__construct(
            'Hoàn tiền ' . number_format($requested, 0, ',', '.') . ' ₫ vượt mức tối đa '
            . number_format($cap, 0, ',', '.') . ' ₫ cho phép của đơn.'
        );
    }
}
