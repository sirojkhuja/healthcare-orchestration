<?php

namespace App\Modules\Integrations\Application\Exceptions;

use RuntimeException;

final class UzumWebhookException extends RuntimeException
{
    public function __construct(
        public readonly string $uzumCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
