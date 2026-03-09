<?php

namespace App\Modules\TenantManagement\Application\Data;

final readonly class LocationSearchResultData
{
    public function __construct(
        public string $type,
        public string $code,
        public string $name,
        public ?string $cityCode,
        public ?string $cityName,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'code' => $this->code,
            'name' => $this->name,
            'city_code' => $this->cityCode,
            'city_name' => $this->cityName,
        ];
    }
}
