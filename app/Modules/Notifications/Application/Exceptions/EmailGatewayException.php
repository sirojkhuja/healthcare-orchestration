<?php

namespace App\Modules\Notifications\Application\Exceptions;

use RuntimeException;

final class EmailGatewayException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
