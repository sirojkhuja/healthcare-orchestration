<?php

namespace App\Modules\Reporting\Application\Commands;

final readonly class CreateReportCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
