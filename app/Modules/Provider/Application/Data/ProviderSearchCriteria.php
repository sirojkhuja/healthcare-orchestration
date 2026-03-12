<?php

namespace App\Modules\Provider\Application\Data;

final readonly class ProviderSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $providerType = null,
        public ?string $clinicId = null,
        public ?bool $hasEmail = null,
        public ?bool $hasPhone = null,
        public int $limit = 25,
    ) {}

    public function normalizedQuery(): ?string
    {
        $query = trim($this->query ?? '');

        return $query !== '' ? mb_strtolower($query) : null;
    }

    public function hasQuery(): bool
    {
        return $this->normalizedQuery() !== null;
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

        $parts = preg_split('/\s+/', $query) ?: [];

        return array_values(array_filter(
            $parts,
            static fn (string $part): bool => $part !== '',
        ));
    }

    /**
     * @return array{
     *     q: string|null,
     *     provider_type: string|null,
     *     clinic_id: string|null,
     *     has_email: bool|null,
     *     has_phone: bool|null,
     *     limit: int
     * }
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'provider_type' => $this->providerType,
            'clinic_id' => $this->clinicId,
            'has_email' => $this->hasEmail,
            'has_phone' => $this->hasPhone,
            'limit' => $this->limit,
        ];
    }
}
