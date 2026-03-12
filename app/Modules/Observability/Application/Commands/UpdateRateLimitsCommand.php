<?php

namespace App\Modules\Observability\Application\Commands;

final readonly class UpdateRateLimitsCommand
{
    /**
     * @param  array<string, array{requests_per_minute: int, burst: int}>  $limits
     */
    public function __construct(public array $limits) {}
}
