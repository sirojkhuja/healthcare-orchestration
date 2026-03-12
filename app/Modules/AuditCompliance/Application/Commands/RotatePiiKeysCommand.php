<?php

namespace App\Modules\AuditCompliance\Application\Commands;

final readonly class RotatePiiKeysCommand
{
    /**
     * @param  list<string>  $fieldIds
     */
    public function __construct(
        public array $fieldIds,
    ) {}
}
