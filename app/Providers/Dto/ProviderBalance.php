<?php

namespace App\Providers\Dto;

/** Số dư tài khoản tại nhà cung cấp (USD). */
final readonly class ProviderBalance
{
    public function __construct(public string $usd)
    {
    }
}
