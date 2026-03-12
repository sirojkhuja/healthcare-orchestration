<?php

namespace App\Modules\AuditCompliance\Application\Commands;

use App\Modules\AuditCompliance\Application\Data\PiiFieldMutationData;

final readonly class SetPiiFieldsCommand
{
    /**
     * @param  list<PiiFieldMutationData>  $fields
     */
    public function __construct(
        public array $fields,
    ) {}
}
