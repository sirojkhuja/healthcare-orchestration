<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class MakeAppointmentRecurringCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $appointmentId,
        public array $attributes,
    ) {}
}
