<?php

namespace App\Modules\Provider\Application\Data;

final readonly class ProviderGroupMemberData
{
    public function __construct(
        public string $providerId,
        public string $firstName,
        public string $lastName,
        public ?string $preferredName,
        public string $providerType,
        public ?string $clinicId,
    ) {}

    public function displayName(): string
    {
        $firstName = $this->preferredName ?? $this->firstName;

        return trim($firstName.' '.$this->lastName);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->providerId,
            'display_name' => $this->displayName(),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'preferred_name' => $this->preferredName,
            'provider_type' => $this->providerType,
            'clinic_id' => $this->clinicId,
        ];
    }
}
