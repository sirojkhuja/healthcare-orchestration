<?php

namespace App\Modules\AuditCompliance\Application\Commands;

final readonly class CreateDataAccessRequestCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
