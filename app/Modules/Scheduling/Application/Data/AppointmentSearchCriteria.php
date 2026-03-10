<?php

namespace App\Modules\Scheduling\Application\Data;

final readonly class AppointmentSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $status = null,
        public ?string $patientId = null,
        public ?string $providerId = null,
        public ?string $clinicId = null,
        public ?string $roomId = null,
        public ?string $scheduledFrom = null,
        public ?string $scheduledTo = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public int $limit = 25,
    ) {}

    public function hasQuery(): bool
    {
        return $this->normalizedQuery() !== null;
    }

    public function normalizedQuery(): ?string
    {
        $query = trim($this->query ?? '');

        return $query !== '' ? mb_strtolower($query) : null;
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        $query = $this->normalizedQuery();

        if ($query === null) {
            return [];
        }

        $tokens = preg_split('/\s+/', $query) ?: [];

        return array_values(array_filter(
            $tokens,
            static fn (string $token): bool => $token !== '',
        ));
    }

    /**
     * @return array{
     *     q: string|null,
     *     status: string|null,
     *     patient_id: string|null,
     *     provider_id: string|null,
     *     clinic_id: string|null,
     *     room_id: string|null,
     *     scheduled_from: string|null,
     *     scheduled_to: string|null,
     *     created_from: string|null,
     *     created_to: string|null,
     *     limit: int
     * }
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'status' => $this->status,
            'patient_id' => $this->patientId,
            'provider_id' => $this->providerId,
            'clinic_id' => $this->clinicId,
            'room_id' => $this->roomId,
            'scheduled_from' => $this->scheduledFrom,
            'scheduled_to' => $this->scheduledTo,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'limit' => $this->limit,
        ];
    }
}
