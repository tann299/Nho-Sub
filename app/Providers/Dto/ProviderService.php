<?php

namespace App\Providers\Dto;

/** Một dịch vụ nhà cung cấp trả về (giá tính USD/1000). */
final readonly class ProviderService
{
    public function __construct(
        public string $id,
        public string $name,
        public string $category,
        public string $description,
        public string $costUsd,
        public int $minQty,
        public int $maxQty,
    ) {
    }
}
