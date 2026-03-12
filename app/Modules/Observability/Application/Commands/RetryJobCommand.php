<?php

namespace App\Modules\Observability\Application\Commands;

final readonly class RetryJobCommand
{
    public function __construct(public string $jobId) {}
}
