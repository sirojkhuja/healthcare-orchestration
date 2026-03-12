<?php

namespace App\Modules\AuditCompliance\Application\Data;

final readonly class ComplianceReportSearchCriteria
{
    public function __construct(
        public ?string $type = null,
        public ?string $status = null,
        public int $limit = 50,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'status' => $this->status,
            'limit' => $this->limit,
        ];
    }
}
