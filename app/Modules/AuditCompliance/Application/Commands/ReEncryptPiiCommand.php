<?php

namespace App\Modules\AuditCompliance\Application\Commands;

final readonly class ReEncryptPiiCommand
{
    /**
     * @param  list<string>  $fieldIds
     */
    public function __construct(
        public array $fieldIds,
    ) {}
}
