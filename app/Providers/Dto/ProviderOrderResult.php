<?php

namespace App\Providers\Dto;

final readonly class ProviderOrderResult
{
    public function __construct(public string $providerOrderId)
    {
    }
}
