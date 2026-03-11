<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class DeletePayerCommand
{
    public function __construct(
        public string $payerId,
    ) {}
}
