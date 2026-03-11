<?php

namespace App\Modules\Integrations\Application\Exceptions;

use RuntimeException;

final class ClickWebhookException extends RuntimeException
{
    public function __construct(
        public readonly int $clickCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
