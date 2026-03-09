<?php

namespace App\Modules\Patient\Application\Commands;

final readonly class CreatePatientCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
