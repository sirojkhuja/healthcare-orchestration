<?php

namespace App\Shared\Application\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class IdempotencyReplayException extends ConflictHttpException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }

    public static function alreadyProcessing(string $operation): self
    {
        return new self(
            message: 'A request with this idempotency key is already being processed.',
            details: [
                'operation' => $operation,
                'reason' => 'request_in_progress',
            ],
        );
    }

    public static function payloadMismatch(string $operation): self
    {
        return new self(
            message: 'The idempotency key was already used for a different request payload.',
            details: [
                'operation' => $operation,
                'reason' => 'payload_mismatch',
            ],
        );
    }
}
