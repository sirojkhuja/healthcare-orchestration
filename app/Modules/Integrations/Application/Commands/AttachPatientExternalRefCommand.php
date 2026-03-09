<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class AttachPatientExternalRefCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $patientId,
        public array $attributes,
    ) {}
}
