<?php

namespace App\Exceptions;

use RuntimeException;

/** Lỗi từ nhà cung cấp — thông điệp KHÔNG bao giờ chứa API key. */
class ProviderException extends RuntimeException
{
}
