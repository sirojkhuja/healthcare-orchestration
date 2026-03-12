<?php

namespace App\Modules\Observability\Application\Commands;

final readonly class ReloadLoggingPipelinesCommand
{
    /**
     * @param  list<string>|null  $pipelines
     */
    public function __construct(public ?array $pipelines) {}
}
