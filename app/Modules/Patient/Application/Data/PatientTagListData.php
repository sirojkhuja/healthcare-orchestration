<?php

namespace App\Modules\Patient\Application\Data;

final readonly class PatientTagListData
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $patientId,
        public array $tags,
    ) {}

    /**
     * @return array{patient_id: string, tags: list<string>}
     */
    public function toArray(): array
    {
        return [
            'patient_id' => $this->patientId,
            'tags' => $this->tags,
        ];
    }
}
