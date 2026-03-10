<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class CreateAppointmentCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
