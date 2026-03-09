<?php

namespace App\Modules\TenantManagement\Application\Data;

final readonly class LocationCityData
{
    public function __construct(
        public string $code,
        public string $name,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
