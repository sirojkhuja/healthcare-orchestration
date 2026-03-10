<?php

namespace App\Modules\Pharmacy\Application\Data;

final readonly class PatientMedicationListCriteria
{
    public function __construct(
        public ?string $status = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array{
     *     status: string|null,
     *     limit: int
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'limit' => $this->limit,
        ];
    }
}
