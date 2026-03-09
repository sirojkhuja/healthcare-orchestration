<?php

namespace App\Modules\Patient\Application\Data;

final readonly class PatientSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $sex = null,
        public ?string $cityCode = null,
        public ?string $districtCode = null,
        public ?string $birthDateFrom = null,
        public ?string $birthDateTo = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public ?bool $hasEmail = null,
        public ?bool $hasPhone = null,
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
     *     sex: string|null,
     *     city_code: string|null,
     *     district_code: string|null,
     *     birth_date_from: string|null,
     *     birth_date_to: string|null,
     *     created_from: string|null,
     *     created_to: string|null,
     *     has_email: bool|null,
     *     has_phone: bool|null,
     *     limit: int
     * }
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'sex' => $this->sex,
            'city_code' => $this->cityCode,
            'district_code' => $this->districtCode,
            'birth_date_from' => $this->birthDateFrom,
            'birth_date_to' => $this->birthDateTo,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'has_email' => $this->hasEmail,
            'has_phone' => $this->hasPhone,
            'limit' => $this->limit,
        ];
    }
}
