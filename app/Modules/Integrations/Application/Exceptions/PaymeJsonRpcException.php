<?php

namespace App\Modules\Integrations\Application\Exceptions;

use RuntimeException;

final class PaymeJsonRpcException extends RuntimeException
{
    public function __construct(
        public readonly int $paymeCode,
        string $message,
        public readonly mixed $errorData = null,
    ) {
        parent::__construct($message);
    }
}
