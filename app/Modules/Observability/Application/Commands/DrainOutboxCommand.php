<?php

namespace App\Modules\Observability\Application\Commands;

final readonly class DrainOutboxCommand
{
    public function __construct(public int $limit) {}
}
