<?php

namespace App\Modules\AuditCompliance\Application\Data;

final readonly class PiiFieldMutationData
{
    public function __construct(
        public string $objectType,
        public string $fieldPath,
        public string $classification,
        public string $encryptionProfile,
        public ?string $notes,
    ) {}
}
