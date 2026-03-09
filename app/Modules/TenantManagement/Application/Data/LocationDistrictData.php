<?php

namespace App\Modules\TenantManagement\Application\Data;

final readonly class LocationDistrictData
{
    public function __construct(
        public string $code,
        public string $cityCode,
        public string $name,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'city_code' => $this->cityCode,
            'name' => $this->name,
        ];
    }
}
