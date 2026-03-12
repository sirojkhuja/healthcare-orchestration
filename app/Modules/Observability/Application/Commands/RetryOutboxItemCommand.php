<?php

namespace App\Modules\Observability\Application\Commands;

final readonly class RetryOutboxItemCommand
{
    public function __construct(public string $outboxId) {}
}
