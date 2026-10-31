<?php

namespace App\Providers\Dto;

final readonly class ProviderOrderStatus
{
    public function __construct(
        public string $status,       // pending|processing|completed|partial|canceled|unknown
        public ?int $startCount = null,
        public ?int $remains = null,
    ) {
    }
}
