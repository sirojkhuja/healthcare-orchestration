<?php

namespace App\Modules\Treatment\Application\Data;

final readonly class BulkEncounterUpdateData
{
    /**
     * @param  list<string>  $updatedFields
     * @param  list<EncounterData>  $encounters
     */
    public function __construct(
        public string $operationId,
        public int $affectedCount,
        public array $updatedFields,
        public array $encounters,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'affected_count' => $this->affectedCount,
            'updated_fields' => $this->updatedFields,
            'encounters' => array_map(
                static fn (EncounterData $encounter): array => $encounter->toArray(),
                $this->encounters,
            ),
        ];
    }
}
