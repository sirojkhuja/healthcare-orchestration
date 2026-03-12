<?php

namespace App\Modules\AuditCompliance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class AuditEventSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $actionPrefix = null,
        public ?string $objectType = null,
        public ?string $objectId = null,
        public ?string $actorType = null,
        public ?string $actorId = null,
        public ?CarbonImmutable $occurredFrom = null,
        public ?CarbonImmutable $occurredTo = null,
        public int $limit = 50,
    ) {}

    public function normalizedQuery(): ?string
    {
        if ($this->query === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($this->query));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'action_prefix' => $this->actionPrefix,
            'object_type' => $this->objectType,
            'object_id' => $this->objectId,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'occurred_from' => $this->occurredFrom?->toIso8601String(),
            'occurred_to' => $this->occurredTo?->toIso8601String(),
            'limit' => $this->limit,
        ];
    }
}
