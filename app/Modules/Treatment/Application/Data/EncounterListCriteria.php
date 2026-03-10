<?php

namespace App\Modules\Treatment\Application\Data;

final readonly class EncounterListCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $status = null,
        public ?string $patientId = null,
        public ?string $providerId = null,
        public ?string $treatmentPlanId = null,
        public ?string $appointmentId = null,
        public ?string $clinicId = null,
        public ?string $encounterFrom = null,
        public ?string $encounterTo = null,
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
        if (! is_string($this->query)) {
            return null;
        }

        $normalized = mb_strtolower(trim($this->query));

        return $normalized !== '' ? $normalized : null;
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

        return array_values(array_filter(preg_split('/\s+/', $query) ?: []));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'status' => $this->status,
            'patient_id' => $this->patientId,
            'provider_id' => $this->providerId,
            'treatment_plan_id' => $this->treatmentPlanId,
            'appointment_id' => $this->appointmentId,
            'clinic_id' => $this->clinicId,
            'encounter_from' => $this->encounterFrom,
            'encounter_to' => $this->encounterTo,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'limit' => $this->limit,
        ];
    }
}
